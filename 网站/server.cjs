'use strict';
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { randomBytes, timingSafeEqual } = require('node:crypto');
const core = require('./core.cjs');
const editable = core.editOptions(core.catalog);
const sessions = new Map();
const rates = new Map();
const TTL = 15 * 60 * 1000;
const MAX_BODY = 18 * 1024 * 1024;
const MAX_TOTAL = 12 * 1024 * 1024;
const publicFiles = { '/': ['index.html', 'text/html; charset=utf-8'], '/app.js': ['app.js', 'text/javascript; charset=utf-8'], '/style.css': ['style.css', 'text/css; charset=utf-8'] };
function fail(message, status = 400) { throw Object.assign(new Error(message), { status }); }
function prune() {
  const now = Date.now();
  for (const [id, s] of sessions) if (s.expires < now) sessions.delete(id);
  for (const [id, r] of rates) if (r.until < now) rates.delete(id);
}
const cleanup = setInterval(prune, 60000); cleanup.unref();
async function body(req) {
  if (!req.headers['content-type']?.startsWith('application/json')) fail('仅接受 JSON 请求。', 415);
  if (Number(req.headers['content-length']) > MAX_BODY) fail('上传内容过大。', 413);
  let size = 0; const chunks = [];
  for await (const chunk of req) {
    size += chunk.length;
    if (size > MAX_BODY) fail('上传内容过大。', 413);
    chunks.push(chunk);
  }
  try { return JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch { fail('请求格式无效。'); }
}
async function snapshot(s, index) {
  if (!Number.isInteger(index) || !s.backup.slots[index]) fail('存档槽位无效。');
  const active = s.backup.slots[index];
  const slot = core.readSlot(new Uint8Array(await active.progress.arrayBuffer()), new Uint8Array(await active.caption.arrayBuffer()));
  const image = core.captionImage(slot.c);
  return { slot, active, data: {
    slots: s.backup.slots.map(x => x.id), index, rupees: slot.rupees, wallet: slot.w.id,
    vitals: slot.vitals, capacities: slot.capacities, counts: slot.counts,
    bottles: slot.bottles.filter(x => x.stock === 1).length,
    wallets: core.wallets.map(({id,label,limit}) => ({id,label,limit})),
    quantities: core.quantityFields.map(({id,label,max}) => ({id,label,max})),
    capacityGroups: core.capacityGroups.map(g => ({id:g.id,label:g.label, tiers:g.tiers.filter(([limit,stock]) => limit > slot.capacities[g.id] || (g.id === 'seeds' && limit < slot.capacities[g.id] && slot.p.int(stock) === 1)).map(([n])=>n)})),
    magicStages: core.magicStages.filter(x => x.max >= slot.vitals.maxMagic).map(({max,label})=>({max,label})),
    voiceLanguages: Array.from(core.voiceLanguages), language: s.options?.language ?? null,
    inventory: core.catalog.inventory.filter(([h])=>slot.p.fields.has(h)).map(([hash,name,category,canAdd,title])=>({hash,name,category,title,count:slot.p.int(hash),canAdd,removalIssue:core.removalIssue(slot.p,hash,editable)})),
    flags: core.catalog.fields.filter(([h,,name])=>editable.flags.has(h)&&slot.p.fields.has(h)&&[0,1].includes(slot.p.int(h))).map(([hash,,name])=>({hash,name,value:Boolean(slot.p.int(hash))})),
    preview: image ? 'data:image/jpeg;base64,' + Buffer.from(await image.arrayBuffer()).toString('base64') : null,
    legacyMagic: slot.legacyMagic, optionError: s.optionError,
  }};
}
const server = http.createServer(async (req,res) => {
  res.setHeader('X-Content-Type-Options','nosniff');
  res.setHeader('Referrer-Policy','no-referrer');
  res.setHeader('Cache-Control','no-store');
  res.setHeader('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'");
  const json = (data,status=200) => { res.writeHead(status,{'Content-Type':'application/json; charset=utf-8'});res.end(JSON.stringify(data)); };
  try {
    const url = new URL(req.url,'http://local');
    if (req.method === 'GET' && publicFiles[url.pathname]) {
      const [name,type] = publicFiles[url.pathname];res.writeHead(200,{'Content-Type':type});res.end(fs.readFileSync(path.join(__dirname,'public',name)));return;
    }
    if (!url.pathname.startsWith('/api/')) fail('未找到页面。',404);
    if (req.method !== 'POST') fail('请求方法不支持。',405);
    // Compare to Host, not a hardcoded deployment domain. No cross-origin API access.
    if (req.headers.origin && new URL(req.headers.origin).host !== req.headers.host) fail('不允许跨站请求。',403);
    if (req.headers['sec-fetch-site'] === 'cross-site') fail('不允许跨站请求。',403);
    prune();
    const ip = req.socket.remoteAddress;
    const rate = rates.get(ip) || {n:0,until:Date.now()+60000}; rates.set(ip,rate);
    if (++rate.n > 90) fail('请求过于频繁，请稍后重试。',429);
    if (process.env.SITE_PASSWORD) {
      const provided = Buffer.from(String(req.headers['x-site-password'] || ''));
      const expected = Buffer.from(process.env.SITE_PASSWORD);
      if (provided.length !== expected.length || !timingSafeEqual(provided,expected)) fail('访问密码错误。',401);
    }
    const input = await body(req);
    if (url.pathname === '/api/open') {
      if (sessions.size >= 20) fail('服务繁忙，请稍后再试。',503);
      if (!Array.isArray(input.files) || !input.files.length || input.files.length > 16) fail('请选择不超过 16 个存档文件。');
      let total = 0; const seen = new Set();
      const files = input.files.map(f => {
        if (typeof f.path !== 'string' || f.path.length > 240 || f.path.split('/').some(x=>!x||x==='.'||x==='..') || /[\\\x00-\x1f]/.test(f.path) || !/(?:^|\/)(progress\.sav|caption\.sav|option\.sav|manifest\.json)$/.test(f.path) || seen.has(f.path)) fail('文件路径无效或重复。');
        seen.add(f.path);
        if (typeof f.contents !== 'string' || !/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(f.contents)) fail('文件编码无效。');
        const bytes = Buffer.from(f.contents,'base64');total += bytes.length;
        if (bytes.length > 2*1024*1024 || total > MAX_TOTAL) fail('存档文件过大。',413);
        return {webkitRelativePath:f.path,arrayBuffer:async()=>Uint8Array.from(bytes).buffer,text:async()=>bytes.toString('utf8')};
      });
      const backup = core.findBackupFiles(files); const s = {backup,expires:Date.now()+TTL};
      if (backup.option) {try {s.options=core.readOptions(new Uint8Array(await backup.option.arrayBuffer()));} catch {s.optionError='option.sav 无法读取，已禁用语音编辑。';}}
      const {data} = await snapshot(s,0);
      const token = randomBytes(32).toString('hex');sessions.set(token,s);json({token,...data});return;
    }
    const token = req.headers['x-session-token']; const s = sessions.get(token);
    if (!s) fail('会话已过期，请重新上传存档。',401);
    s.expires=Date.now()+TTL;
    if (url.pathname === '/api/close') {sessions.delete(token);json({ok:true});return;}
    const {slot,active,data} = await snapshot(s,input.index);
    if (url.pathname === '/api/slot') {json(data);return;}
    if (url.pathname === '/api/fields') {
      const search=String(input.search||'').toLowerCase().slice(0,200);
      const rows=core.catalog.fields.filter(([h,t,n])=>slot.p.fields.has(h)&&(!input.named||n)&&[n,h.toString(16),core.catalog.types[t]].join(' ').toLowerCase().includes(search));
      const page=Math.max(0,Math.min(1000,Number(input.page)||0));
      json({total:rows.length,rows:rows.slice(page*80,page*80+80).map(([h,t,n])=>({name:n||'—',hash:'0x'+h.toString(16),type:core.catalog.types[t],value:core.fieldValue(slot.p,h,core.catalog.types[t])}))});return;
    }
    if (url.pathname !== '/api/export') fail('接口不存在。',404);
    const changes=input.changes;
    if (!changes || !['additions','removals','clearAcquisitions','flags'].every(k=>Array.isArray(changes[k])&&changes[k].length<=10000)) fail('修改数据无效。');
    const after=core.updateSlot(slot,changes,editable);
    const edits=core.slotEdits(active,slot,after);
    if (s.options && input.language !== s.options.language) edits.push({path:'option.sav',before:s.options.save.bytes,after:core.updateOptions(s.options,input.language)});
    const {name,entries}=core.exportLayout(active,edits);let warning='';
    if (s.backup.manifest) {
      try {entries.push(['manifest.json',await core.patchManifest(await s.backup.manifest.text(),edits)]);} catch {warning='备份清单不匹配，已跳过 manifest.json。';}
    }
    const bytes=Buffer.from(await core.zip(entries).arrayBuffer());
    json({name:name+'.zip',contents:bytes.toString('base64'),warning});
  } catch (e) {
    // No stack traces, uploaded data, or request bodies in responses/logs.
    if (!res.headersSent) json({error:e.status?e.message: '无法处理存档或修改，请检查文件格式和输入值。'},e.status||400);
    else res.end();
  }
});
server.requestTimeout=30000;server.headersTimeout=15000;
if (require.main===module) server.listen(Number(process.env.PORT)||3000,'0.0.0.0',()=>console.log('网站已启动，端口 '+(process.env.PORT||3000)));
module.exports={server,sessions};
