const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
(async()=>{
 const base=process.env.PHP_TEST_URL||'http://127.0.0.1:8123';
 const config=await(await fetch(base+'/games-api.php?action=config')).json();
 for(const [game,g] of Object.entries(config.games)){
  if(game==='oot')continue;
  const call=async(action,data,token='')=>{const r=await fetch(base+'/games-api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json','X-Session-Token':token},body:JSON.stringify({game,...data})});return {status:r.status,data:await r.json()};};
  const bytes=fs.readFileSync(path.join(__dirname,'../塞尔达存档修改器',game,g.filename));
  const opened=await call('open',{files:[{path:g.filename,contents:bytes.toString('base64')}]});assert.equal(opened.status,200,JSON.stringify(opened.data));
  const {token}=opened.data;
  const output=await call('export',{changes:{fields:{},collections:{}}},token);assert.equal(output.status,200,JSON.stringify(output.data));assert.deepEqual(Buffer.from(output.data.contents,'base64'),bytes);
  assert.equal((await call('slot',{game:'not-the-same-game'},token)).status,400);
  assert.equal((await call('close',{},token)).status,200);
  assert.equal((await call('slot',{},token)).status,401);
  console.log(game+' HTTP upload/export/isolation/cleanup OK');
 }
 for(const file of ['index.php','editor.php','game.php?game=zelda-totk'])assert.equal((await fetch(base+'/'+file)).status,200);
})().catch(e=>{console.error(e);process.exitCode=1;});
