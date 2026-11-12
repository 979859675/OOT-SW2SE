const {test,after,before}=require('node:test');
const assert=require('node:assert/strict');
const {server,sessions}=require('./server.cjs');
const {fixture}=require('../tests/editor.test.cjs');
const core=require('./core.cjs');
let base;
before(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));base='http://127.0.0.1:'+server.address().port;});
after(()=>new Promise(r=>server.close(r)));
async function post(action,data,token='',headers={}){const r=await fetch(base+'/api/'+action,{method:'POST',headers:{'Content-Type':'application/json','X-Session-Token':token,...headers},body:JSON.stringify(data)});return {status:r.status,data:await r.json()};}
const files=f=>[['progress.sav',f.p],['caption.sav',f.c]].map(([name,b])=>({path:'Save/'+name,contents:Buffer.from(b).toString('base64')}));
test('only explicit frontend files are public; no source/catalog leak',async()=>{
 assert.equal((await fetch(base)).status,200);
 for(const route of ['/core.cjs','/server.cjs','/../editor.html','/editor.html','/.git/config','/package.json'])assert.equal((await fetch(base+route)).status,404);
 const js=await(await fetch(base+'/app.js')).text();assert.ok(!js.includes('function updateSlot'));assert.ok(!js.includes('catalog.fields'));
});
test('upload, slot selection, field inspector, export bytes, and explicit deletion',async()=>{
 const f=fixture();const opened=await post('open',{files:files(f)});assert.equal(opened.status,200);const s=opened.data;
 assert.ok(s.token);assert.equal(s.inventory.find(x=>x.name==='Item_Boomerang').title,'回旋镖');
 assert.equal((await post('slot',{index:0},s.token)).status,200);
 assert.equal((await post('fields',{index:0,search:'',page:0},s.token)).data.rows.length,80);
 const changes={wallet:s.wallet,rupees:123,vitals:s.vitals,bottles:s.bottles,amounts:s.counts,upgrades:{},additions:[],removals:[],clearAcquisitions:[],flags:[]};
 const exported=await post('export',{index:0,changes,language:null},s.token);assert.equal(exported.status,200);assert.match(exported.data.name,/\.zip$/);
 const zip=Buffer.from(exported.data.contents,'base64');assert.equal(zip.readUInt32LE(0),0x04034b50);
 const size=zip.readUInt32LE(18),nameLength=zip.readUInt16LE(26),extra=zip.readUInt16LE(28),start=30+nameLength+extra;
 const save=zip.subarray(start,start+size);const original=core.readSlot(f.p,f.c);const expected=core.updateSlot(original,changes,core.editOptions(core.catalog))[0];assert.deepEqual(save,Buffer.from(expected));
 assert.equal((await post('close',{},s.token)).status,200);assert.ok(!sessions.has(s.token));assert.equal((await post('slot',{index:0},s.token)).status,401);
});
test('reject invalid paths, malformed saves, invalid sessions and cross-origin requests',async()=>{
 const f=fixture();const bad=files(f);bad[0].path='../progress.sav';assert.equal((await post('open',{files:bad})).status,400);
 assert.equal((await post('open',{files:[{path:'Save/progress.sav',contents:'????'}]})).status,400);
 assert.equal((await post('slot',{index:0},'invalid')).status,401);
 assert.equal((await post('open',{files:files(f)},'',{Origin:'https://evil.example'})).status,403);
 const old=process.env.SITE_PASSWORD;process.env.SITE_PASSWORD='test-only-password';try{
 assert.equal((await post('open',{files:files(f)})).status,401);
 const r=await post('open',{files:files(f)},'',{'X-Site-Password':'test-only-password'});assert.equal(r.status,200);sessions.delete(r.data.token);
 }finally{if(old===undefined)delete process.env.SITE_PASSWORD;else process.env.SITE_PASSWORD=old;}
});


test('connection settings report only whether an access password is required', async () => {
 const old=process.env.SITE_PASSWORD;
 try {
  delete process.env.SITE_PASSWORD;
  let r=await fetch(base+'/api/config');
  assert.deepEqual(await r.json(),{service:'oot-sw2se-web',passwordRequired:false});
  process.env.SITE_PASSWORD='test-config-secret';
  r=await fetch(base+'/api/config');
  const text=await r.text();assert.ok(!text.includes('test-config-secret'));
  assert.deepEqual(JSON.parse(text),{service:'oot-sw2se-web',passwordRequired:true});
 } finally {if(old===undefined)delete process.env.SITE_PASSWORD;else process.env.SITE_PASSWORD=old;}
});

test('browser reports HTML/proxy/network failures without exposing raw response text', async () => {
 const {requestJson}=require('./public/api-client.js');
 await assert.rejects(()=>requestJson('/api/open',{},async()=>new Response('<html>private upstream page</html>',{status:502,headers:{'Content-Type':'text/html'}})),/接口返回了网页而不是 JSON/);
 await assert.rejects(()=>requestJson('/api/open',{},async()=>new Response('too large',{status:413})),/上传被代理服务器拒绝/);
 await assert.rejects(()=>requestJson('/api/open',{},async()=>{throw Error('offline');}),/无法连接后端/);
 await assert.rejects(()=>requestJson('/api/open',{},async()=>new Response('{',{headers:{'Content-Type':'application/json'}})),/无效的 JSON/);
 await assert.rejects(()=>requestJson('/api/open',{},async()=>new Response(JSON.stringify({error:'访问密码错误。'}),{status:401,headers:{'Content-Type':'application/json'}})),/访问密码错误/);
 assert.deepEqual(await requestJson('/api/config',{},async()=>new Response('{"ok":true}',{headers:{'Content-Type':'application/json'}})),{ok:true});
});

test('browser rejects incomplete manual selection before upload', () => {
 const {validateFileSelection}=require('./public/api-client.js');
 assert.throws(()=>validateFileSelection([{path:'Save/caption.sav'}]),/同时选中这两个文件/);
 assert.throws(()=>validateFileSelection([{path:'a/progress.sav'},{path:'b/caption.sav'}]),/同一存档目录/);
 assert.doesNotThrow(()=>validateFileSelection([{path:'Save/progress.sav'},{path:'Save/caption.sav'}]));
 assert.doesNotThrow(()=>validateFileSelection([{path:'Backup/files/slot_00/progress.sav'},{path:'Backup/files/slot_00/caption.sav'}]));
});
