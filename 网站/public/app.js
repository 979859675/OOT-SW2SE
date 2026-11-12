'use strict';
const $ = id => document.getElementById(id);
let token='', state, dirty=false, flagPage=0, fieldPage=0;
const adds=new Set(), removes=new Set(), clears=new Set(), flags=new Map();
const el=(tag,text)=>{const e=document.createElement(tag);e.textContent=text;return e;};
async function api(action,data={}) {
  const r=await fetch('/api/'+action,{method:'POST',headers:{'Content-Type':'application/json','X-Session-Token':token,'X-Site-Password':$('password').value},body:JSON.stringify(data)});
  const result=await r.json();if(!r.ok)throw Error(result.error||'请求失败');return result;
}
function message(e){$('status').textContent=e.message;}
function choices(select,values){select.replaceChildren(...values.map(([v,t])=>new Option(t,v)));}
function check(checked,onchange,label){const i=document.createElement('input');i.type='checkbox';i.checked=checked;i.setAttribute('aria-label',label);i.addEventListener('change',()=>{dirty=true;onchange(i.checked);});return i;}
function cell(row,value){const td=document.createElement('td');td.append(value instanceof Node?value:document.createTextNode(String(value)));row.append(td);}
function inventory(){
 const q=$('itemSearch').value.toLowerCase(),filter=$('itemFilter').value;
 $('itemRows').replaceChildren();
 for(const x of state.inventory){
  if(![x.title,x.name,x.category].join(' ').toLowerCase().includes(q)||(filter==='owned'&&!x.count)||(filter==='missing'&&(!x.canAdd||x.count)))continue;
  const tr=el('tr',''),title=el('div',x.title);title.append(el('small',x.category+' · '+x.name));cell(tr,title);cell(tr,x.count);
  cell(tr,x.canAdd&&!x.count?check(adds.has(x.hash),v=>v?adds.add(x.hash):adds.delete(x.hash),'添加 '+x.title):'—');
  if(x.count===1&&!x.removalIssue){
   cell(tr,check(removes.has(x.hash),v=>{if(v)removes.add(x.hash);else{removes.delete(x.hash);clears.delete(x.hash);}inventory();},'移除 '+x.title));
   const c=check(clears.has(x.hash),v=>v?clears.add(x.hash):clears.delete(x.hash),'清除 '+x.title+' 获取标记');c.disabled=!removes.has(x.hash);cell(tr,c);
  }else{const note=el('span','—');note.title=x.removalIssue;cell(tr,note);cell(tr,'—');}
  $('itemRows').append(tr);
 }
}
function renderFlags(){
 const q=$('flagSearch').value.toLowerCase(),rows=state.flags.filter(x=>[x.name,x.hash.toString(16)].join(' ').toLowerCase().includes(q));
 flagPage=Math.min(flagPage,Math.max(0,Math.ceil(rows.length/80)-1));$('flagRows').replaceChildren();
 for(const x of rows.slice(flagPage*80,flagPage*80+80)){const tr=el('tr','');cell(tr,x.name);cell(tr,check(flags.get(x.hash)??x.value,v=>v===x.value?flags.delete(x.hash):flags.set(x.hash,v),x.name));$('flagRows').append(tr);}
 $('flagCount').textContent=`共 ${rows.length} 个标记 · 第 ${flagPage+1} 页 · 已修改 ${flags.size} 项`;$('flagPrev').disabled=!flagPage;$('flagNext').disabled=(flagPage+1)*80>=rows.length;
}
function limits(){const w=state.wallets.find(x=>x.id===$('wallet').value);$('rupees').max=w.limit;$('hearts').max=$('maxHearts').value;$('magic').max=$('maxMagic').value;}
function render(){
 adds.clear();removes.clear();clears.clear();flags.clear();dirty=false;flagPage=0;fieldPage=0;
 $('workspace').hidden=false;choices($('slot'),state.slots.map((s,i)=>[i,s]));$('slot').value=state.index;
 choices($('wallet'),state.wallets.map(w=>[w.id,w.label+' · '+w.limit]));$('wallet').value=state.wallet;
 for(const k of ['rupees','bottles'])$(k).value=state[k];
 for(const k of ['hearts','maxHearts','magic'])$(k).value=state.vitals[k];
 choices($('maxMagic'),state.magicStages.map(x=>[x.max,x.label]));$('maxMagic').value=state.magicStages.some(x=>x.max===state.vitals.maxMagic)?state.vitals.maxMagic:state.magicStages.at(-1).max;
 choices($('language'),state.voiceLanguages);$('language').disabled=state.language===null;if(state.language!==null)$('language').value=state.language;
 $('quantities').replaceChildren();for(const x of state.quantities){const l=el('label',x.label),i=document.createElement('input');i.type='number';i.id='q-'+x.id;i.min=0;i.step=1;i.required=true;i.value=state.counts[x.id];l.append(i);$('quantities').append(l);}
 $('capacities').replaceChildren();for(const x of state.capacityGroups){const l=el('label',x.label),i=document.createElement('select');i.id='c-'+x.id;choices(i,[['auto','自动 · 当前容量 '+state.capacities[x.id]],...x.tiers.map(n=>[n,'容量 '+n])]);l.append(i);$('capacities').append(l);}
 $('summary').textContent=`${state.slots[state.index]} · ${state.rupees} 卢比 · ${state.vitals.hearts}/${state.vitals.maxHearts} 颗心 · ${state.vitals.magic}/${state.vitals.maxMagic} MP`;
 $('notice').textContent=(state.optionError||'')+(state.legacyMagic?' 此存档使用旧版魔力单位，导出时将转换。':'');$('preview').hidden=!state.preview;if(state.preview)$('preview').src=state.preview;else $('preview').removeAttribute('src');
 $('fieldRows').replaceChildren();$('fieldCount').textContent='';$('result').textContent='';inventory();renderFlags();limits();
}
async function upload(list){
 if(dirty&&!confirm('打开新存档会丢弃未导出的修改，是否继续？'))return;
 $('status').textContent='正在上传并校验，请稍候…';
 try{
 const files=[];for(const file of list){if(!/^(progress\.sav|caption\.sav|option\.sav|manifest\.json)$/.test(file.name))continue;if(file.size>2*1024*1024)throw Error('单文件不能超过 2 MB。');if(files.length>=16)throw Error('文件数量不能超过 16 个。');const bytes=new Uint8Array(await file.arrayBuffer());let binary='';for(let i=0;i<bytes.length;i+=8192)binary+=String.fromCharCode(...bytes.subarray(i,i+8192));files.push({path:file.webkitRelativePath||'Save/'+file.name,contents:btoa(binary)});}
 if(token){await api('close');token='';$('workspace').hidden=true;}
 const result=await api('open',{files});token=result.token;state=result;render();$('status').textContent='存档已加载。原始文件不会被覆盖。';
 }catch(e){message(e);}
}
$('folder').addEventListener('change',e=>upload(e.target.files));$('files').addEventListener('change',e=>upload(e.target.files));
$('slot').addEventListener('change',async()=>{const previous=state.index;if(dirty&&!confirm('切换槽位会重置未导出的修改，是否继续？')){$('slot').value=previous;return;}try{state=await api('slot',{index:Number($('slot').value)});render();}catch(e){$('slot').value=previous;message(e);}});
$('close').addEventListener('click',async()=>{if(dirty&&!confirm('清除会话将丢弃未导出的修改，是否继续？'))return;try{if(token)await api('close');token='';state=null;dirty=false;$('workspace').hidden=true;$('status').textContent='服务器会话已清除。';}catch(e){message(e);}});
$('reset').addEventListener('click',()=>{if(!dirty||confirm('重置所有修改？'))render();});
$('itemSearch').addEventListener('input',inventory);$('itemFilter').addEventListener('change',inventory);
$('flagSearch').addEventListener('input',()=>{flagPage=0;renderFlags();});$('flagPrev').addEventListener('click',()=>{flagPage--;renderFlags();});$('flagNext').addEventListener('click',()=>{flagPage++;renderFlags();});
$('edit').addEventListener('input',e=>{if(!['itemSearch','flagSearch','fieldSearch'].includes(e.target.id))dirty=true;});for(const id of ['wallet','maxHearts','maxMagic'])$(id).addEventListener('change',limits);
async function fields(){try{const data=await api('fields',{index:state.index,search:$('fieldSearch').value,page:fieldPage});$('fieldRows').replaceChildren();for(const x of data.rows){const tr=el('tr','');for(const k of ['name','hash','type','value'])cell(tr,x[k]);$('fieldRows').append(tr);}$('fieldCount').textContent=`共 ${data.total} 个字段 · 第 ${fieldPage+1} 页`;$('fieldPrev').disabled=!fieldPage;$('fieldNext').disabled=(fieldPage+1)*80>=data.total;}catch(e){message(e);}}
$('fieldFind').addEventListener('click',()=>{fieldPage=0;fields();});$('fieldPrev').addEventListener('click',()=>{fieldPage=Math.max(0,fieldPage-1);fields();});$('fieldNext').addEventListener('click',()=>{fieldPage++;fields();});
$('edit').addEventListener('submit',async e=>{e.preventDefault();const n=id=>Number($(id).value);if((removes.size||flags.size)&&!confirm(`将移除 ${removes.size} 个物品、清除 ${clears.size} 项获取记录、修改 ${flags.size} 个进度标记。确认生成补丁？`))return;$('download').disabled=true;$('result').textContent='服务器正在生成补丁…';try{
 const changes={wallet:$('wallet').value,rupees:n('rupees'),bottles:n('bottles'),vitals:Object.fromEntries(['hearts','maxHearts','magic','maxMagic'].map(k=>[k,n(k)])),amounts:Object.fromEntries(state.quantities.map(x=>[x.id,n('q-'+x.id)])),upgrades:Object.fromEntries(state.capacityGroups.filter(x=>$('c-'+x.id).value!=='auto').map(x=>[x.id,n('c-'+x.id)])),additions:[...adds],removals:[...removes],clearAcquisitions:[...clears],flags:[...flags]};
 const data=await api('export',{index:state.index,changes,language:state.language===null?null:n('language')});const bytes=Uint8Array.from(atob(data.contents),c=>c.charCodeAt(0)),url=URL.createObjectURL(new Blob([bytes],{type:'application/zip'})),a=document.createElement('a');a.href=url;a.download=data.name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);$('result').textContent='已生成补丁并请求浏览器下载。'+data.warning;dirty=false;
 }catch(e){$('result').textContent=e.message;}finally{$('download').disabled=false;}});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
