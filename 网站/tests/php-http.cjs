const assert=require('node:assert/strict');
const fs=require('node:fs');
const {spawn}=require('node:child_process');
const {setTimeout:delay}=require('node:timers/promises');
const path=require('node:path');
async function main(){
 const base=process.env.PHP_TEST_URL||'http://127.0.0.1:8123';
 const child=process.env.PHP_TEST_URL?null:spawn(process.env.PHP_BIN||'php',['-S','127.0.0.1:8123','-t',path.join(__dirname,'../PHP版')],{stdio:['ignore','ignore','inherit']});
 try{
  let ready=false;for(let n=0;n<50;n++){try{const r=await fetch(base+'/api.php?action=config');if(r.ok){ready=true;break;}}catch{}await delay(100);}assert.ok(ready,'PHP web server did not start');
  const config=await(await fetch(base+'/api.php?action=config')).json();assert.equal(config.service,'oot-sw2se-web');assert.equal(config.runtime,'php');
  const request=async(action,input={},token='',extra={})=>{const r=await fetch(base+'/api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json','X-Session-Token':token,...extra},body:JSON.stringify(input)});return{status:r.status,data:await r.json()};};
  for(const file of ['private/data.php','private/config.php','private/core.php','private/web.php']){const r=await fetch(base+'/'+file);assert.equal(r.status,404);assert.equal(await r.text(),'');}
  const vectors=JSON.parse(fs.readFileSync(path.join(__dirname,'../../.build/php-vectors.json')));
  const v=vectors.cases[1];const files=[{path:'Save/progress.sav',contents:v.progress},{path:'Save/caption.sav',contents:v.caption}];
  assert.equal((await request('open',{files},'',{Origin:'https://evil.example'})).status,403);
  assert.equal((await request('open',{files:[{...files[0],path:'../progress.sav'},files[1]]})).status,400);
  assert.equal((await request('open',{files:[files[1]]})).status,400);
  assert.equal((await request('open',{files:[{...files[0],contents:'????'},files[1]]})).status,400);
  const open=await request('open',{files});assert.equal(open.status,200,JSON.stringify(open.data));const token=open.data.token;assert.match(token,/^[a-f0-9]{64}$/);assert.equal(open.data.wallet,'Item_Wallet_Small');
  assert.equal((await request('slot',{index:0},token)).status,200);
  assert.equal((await request('fields',{index:0,page:0},token)).data.rows.length,80);
  assert.equal((await request('slot',{index:9},token)).status,400);
  const result=await request('export',{index:0,changes:v.changes,language:null},token);assert.equal(result.status,200,JSON.stringify(result.data));
  const zip=Buffer.from(result.data.contents,'base64');let offset=0,index=0;
  while(zip.readUInt32LE(offset)===0x04034b50){const size=zip.readUInt32LE(offset+18),nl=zip.readUInt16LE(offset+26),extra=zip.readUInt16LE(offset+28);const start=offset+30+nl+extra;assert.deepEqual(zip.subarray(start,start+size),Buffer.from(v.expected[index++],'base64'));offset=start+size;}assert.equal(index,2);
  const invalid={...v.changes,rupees:9999};assert.equal((await request('export',{index:0,changes:invalid},token)).status,400);
  assert.equal((await request('close',{},token)).status,200);assert.equal((await request('slot',{index:0},token)).status,401);
  assert.equal((await request('slot',{index:0},'f'.repeat(64))).status,401);
  console.log('PASS: PHP HTTP config, private source guards, upload, slots, fields, ZIP byte parity, rejection and session deletion');
 }finally{child?.kill();}
}
main().catch(e=>{console.error(e);process.exitCode=1;});
