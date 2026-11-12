'use strict';
// Presentation only: all binary parsing, version checks and writes happen in PHP.
(async()=>{
 const id=new URLSearchParams(location.search).get('game');
 const e=(tag,text)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;return node;};
 let token='',state,dirty=false,busy=false,registry={},active='home';
 const changed={},collections={};
 const shell=e('section');shell.id='php-shell';const bar=e('div');bar.className='php-bar';const home=e('a','← 游戏首页');home.href='index.php';const selector=e('select');selector.setAttribute('aria-label','切换游戏');const badge=e('span','简体中文 · PHP 后端');bar.append(home,selector,badge);shell.append(bar);
 const info=e('p','上传存档到服务器临时目录处理，空闲 15 分钟后会话失效。请保留原始备份。');shell.append(info);
 const passwordLabel=e('label','网站访问密码 '),password=e('input');password.type='password';password.autocomplete='current-password';passwordLabel.append(password);passwordLabel.hidden=true;shell.append(passwordLabel);
 const controls=e('div');controls.className='php-bar';const choose=e('input');choose.type='file';const save=e('button','保存更改并下载'),close=e('button','清除临时存档'),reset=e('button','重置修改');save.disabled=close.disabled=reset.disabled=true;controls.append(choose,save,reset,close);shell.append(controls);const status=e('p','正在检测 PHP 服务…');status.id='php-status';status.setAttribute('role','status');shell.append(status);document.body.prepend(shell);
 const editor=document.getElementById('the-editor')||document.body.appendChild(e('main'));editor.hidden=true;
 // Keep upstream markup/styles, but never run its client-side save editor.
 for(const x of document.querySelectorAll('script:not([src="assets/multi-game.js"]),.dialog,[id^="modal-"],#container-startup,#container-savegameslots,#toolbar,#add-item-button,#toasts-container'))if(x!==shell)x.hidden=true;
 for(const x of editor.querySelectorAll('input,select,button,textarea')){x.disabled=true;x.title='该原版控件尚未接入 PHP；请使用已启用的控件。';}
 const tabs=e('nav');tabs.id='php-tabs';shell.after(tabs);const pages=new Map();
 const originalHome=editor.querySelector('#tab-home');if(originalHome)pages.set('home',originalHome);else pages.set('home',editor);
 let base=originalHome||editor;
 function displayError(error){status.textContent=error.message;}
 async function api(action,data={}){
  let r;try{r=await fetch('games-api.php?action='+action,{method:action==='config'?'GET':'POST',headers:{'Content-Type':'application/json','X-Session-Token':token,'X-Site-Password':password.value},...(action==='config'?{}:{body:JSON.stringify({game:id,...data})})});}catch{throw Error('无法连接 PHP 后端，请检查网站 PHP 设置。');}
  if(!r.headers.get('content-type')?.includes('application/json'))throw Error(`后端返回网页而不是 JSON（HTTP ${r.status}），请检查 PHP 版本、上传限制和网站配置。`);let dataOut;try{dataOut=await r.json();}catch{throw Error('PHP 返回数据无效，请检查错误日志。');}if(!r.ok)throw Error(dataOut.error||'请求失败');return dataOut;
 }
 function touched(){dirty=true;status.textContent='有未导出的修改。';}
 function widget(f,value,onchange){
  let input;
  if(f.options){input=e('select');for(const o of f.options)input.add(new Option(o.label,o.value));if(!f.options.some(o=>String(o.value)===String(value)))input.add(new Option('原始值：'+value,value));input.value=String(value);}
  else {input=e('input');input.type=f.type==='bool'?'checkbox':(['str64','wstr16','wstr10'].includes(f.type)?'text':'number');if(f.type==='bool')input.checked=value;else input.value=Array.isArray(value)?value.join(', '):value;if(input.type==='number'){input.min=f.min??0;input.max=f.max??2147483647;input.step=f.type==='f32'?'any':1;}else if(input.type==='text')input.maxLength=f.type==='str64'?63:f.type==='wstr10'?9:15;}
  input.disabled=Boolean(f.readonly);input.setAttribute('aria-label',f.label);input.addEventListener('change',()=>{if(!input.checkValidity()){input.reportValidity();return;}const v=f.type==='bool'?input.checked:(['str64','wstr16','wstr10'].includes(f.type)?input.value:Number(input.value));onchange(v);touched();});return input;
 }
 function showPage(key){active=key;for(const [k,page]of pages){page.hidden=k!==key;page.style.display=k===key?'block':'none';}for(const button of tabs.children)button.classList.toggle('active',button.dataset.key===key);}
 function pageFor(key,title){let page=editor.querySelector('#tab-'+key);if(!page){page=e('section');page.className='php-page';editor.append(page);}page.hidden=true;pages.set(key,page);const button=e('button',title);button.dataset.key=key;button.addEventListener('click',()=>showPage(key));tabs.append(button);return page;}
 function collectionState(key){return collections[key]||(collections[key]={rows:new Map(),remove:new Set(),add:[]});}
 function fieldLabel(f,input){const label=e('label',f.label);label.append(input);return label;}
 function renderCollection(c){
  const page=pageFor(c.id,c.title);page.replaceChildren(e('h3',c.title));const count=e('p',`${c.rows.length} / ${c.capacity} · 点击物品名称可编辑属性`);page.append(count);
  const search=e('input');search.type='search';search.placeholder='搜索中文名称或物品 ID';search.className='php-search';search.setAttribute('aria-label','搜索'+c.title);page.append(search);const rows=e('div');page.append(rows);const pager=e('div');pager.className='php-pagination';const prev=e('button','上一页'),next=e('button','下一页'),counter=e('span');pager.append(prev,counter,next);page.append(pager);let index=0;
  if(c.canResize){const line=e('div'),list=e('select');for(const o of c.available)list.add(new Option(o.label+' · '+o.value,o.value));const add=e('button','添加所选物品'),queue=e('small');add.addEventListener('click',()=>{const changes=collectionState(c.id);if(c.rows.length-changes.remove.size+changes.add.length>=c.capacity){status.textContent='物品栏已满。';return;}changes.add.push(list.value);queue.textContent='待添加 '+changes.add.length+' 项（导出后生效）';touched();});line.append(list,add,queue);page.append(line);}
  function editRow(row){
   const modal=e('dialog');modal.className='php-dialog';modal.append(e('h3',row.title));const form=e('div');form.className='php-fields';modal.append(form);const pending=collectionState(c.id).rows.get(row.index)||{};
   for(const f of c.fields){const spec=f.id==='id'?{...f,options:c.available.length?c.available:undefined,readonly:!c.canResize}:f;const value=pending[f.id]??row.values[f.id];const w=widget(spec,value,v=>{const cs=collectionState(c.id);const fields=cs.rows.get(row.index)||{};if(v===row.values[f.id])delete fields[f.id];else fields[f.id]=v;cs.rows.set(row.index,fields);});form.append(fieldLabel(f,w));}
   const done=e('button','完成');done.addEventListener('click',()=>modal.close());modal.append(done);modal.addEventListener('close',()=>{modal.remove();draw();});document.body.append(modal);modal.showModal();
  }
  function draw(){const q=search.value.toLowerCase(),matches=c.rows.filter(r=>[r.title,r.values.id,r.values.name].join(' ').toLowerCase().includes(q));index=Math.max(0,Math.min(index,Math.ceil(matches.length/30)-1));rows.replaceChildren();
   for(const row of matches.slice(index*30,index*30+30)){const item=e('div');item.className='row row-item php-row';const image=e('img');image.alt='';image.loading='lazy';if(id==='zelda-totk'&&c.id!=='horses'){image.src=`assets/games/${id}/assets/item_icons/${c.id}/${row.values.id}.png`;image.addEventListener('error',()=>{image.hidden=true;},{once:true});}else image.hidden=true;item.append(image);
    const name=e('div');const title=e('button',row.title);title.addEventListener('click',()=>editRow(row));name.append(title,e('small',row.values.id||''));item.append(name);const props=e('div');props.className='php-properties';
    const pending=collectionState(c.id).rows.get(row.index)||{};for(const f of c.fields.filter(f=>['quantity','durability','name'].includes(f.id))){props.append(fieldLabel(f,widget(f,pending[f.id]??row.values[f.id],v=>{const cs=collectionState(c.id);const values=cs.rows.get(row.index)||{};if(v===row.values[f.id])delete values[f.id];else values[f.id]=v;cs.rows.set(row.index,values);})));}
    if(c.canResize){const remove=e('button',collectionState(c.id).remove.has(row.index)?'撤销移除':'移除');remove.addEventListener('click',()=>{const cs=collectionState(c.id);if(cs.remove.has(row.index))cs.remove.delete(row.index);else cs.remove.add(row.index);touched();draw();});props.append(remove);item.classList.toggle('php-removed',collectionState(c.id).remove.has(row.index));}item.append(props);rows.append(item);
   }counter.textContent=`${matches.length} 项 · 第 ${index+1} 页`;prev.disabled=index===0;next.disabled=(index+1)*30>=matches.length;
  }
  search.addEventListener('input',()=>{index=0;draw();});prev.addEventListener('click',()=>{index--;draw();});next.addEventListener('click',()=>{index++;draw();});draw();
 }
 function render(){
  for(const k of Object.keys(changed))delete changed[k];for(const k of Object.keys(collections))delete collections[k];dirty=false;tabs.replaceChildren();pages.clear();
  for(const x of editor.querySelectorAll('[id^="tab-"]'))x.hidden=true;
  base=originalHome||editor;if(!originalHome)base.replaceChildren();pages.set('home',base);const homeButton=e('button','基本数值');homeButton.dataset.key='home';homeButton.addEventListener('click',()=>showPage('home'));tabs.append(homeButton);
  for(const x of base.querySelectorAll('input,select,button')){x.disabled=true;}
  base.querySelectorAll('.php-fields,.php-warning').forEach(x=>x.remove());const extras=e('div');extras.className='php-fields';
  for(const f of state.fields){const w=widget(f,f.value,v=>{if(v===f.value)delete changed[f.id];else changed[f.id]=v;});let existing=base.querySelector('[id="number-'+f.id+'"],[id="select-'+f.id+'"],[id="text-'+f.id+'"]');
   if(existing){w.id=existing.id;w.className=existing.className;existing.replaceWith(w);const label=base.querySelector('label[for="'+w.id+'"]');if(label)label.textContent=f.label;}
   else extras.append(fieldLabel(f,w));
  }
  base.append(extras);const note=e('div');note.className='php-warning';note.textContent=state.notes.join(' ');base.prepend(note);
  for(const c of state.collections)renderCollection(c);
  editor.hidden=false;editor.classList.remove('hidden','hide');editor.classList.add('php-active');save.disabled=close.disabled=reset.disabled=false;showPage('home');status.textContent='已加载：'+state.version+'。只启用已迁移的控件，未接入的原版高级控件保持禁用。';
 }
 async function clear(){if(token)await api('close');token='';state=null;dirty=false;editor.hidden=true;editor.classList.remove('php-active');tabs.replaceChildren();save.disabled=close.disabled=reset.disabled=true;}
 async function navigate(url){if(dirty&&!confirm('切换游戏会丢弃未导出的修改，是否继续？'))return;try{await clear();location.href=url;}catch(error){displayError(error);}}
 selector.addEventListener('change',()=>{const game=selector.value;selector.value=id;navigate(game==='oot'?'editor.php':'game.php?game='+encodeURIComponent(game));});home.addEventListener('click',event=>{event.preventDefault();navigate('index.php');});
 choose.addEventListener('change',async()=>{const file=choose.files[0];if(!file||busy)return;if(dirty&&!confirm('重新上传会重置修改，是否继续？'))return;if(file.name!==registry[id].filename){status.textContent='请选择 '+registry[id].filename+'，不是其他游戏或预览文件。';choose.value='';return;}if(file.size>8*1024*1024){status.textContent='文件超过 8 MiB。';return;}busy=true;choose.disabled=true;try{status.textContent='正在上传并解析…';const bytes=new Uint8Array(await file.arrayBuffer());let binary='';for(let i=0;i<bytes.length;i+=8192)binary+=String.fromCharCode(...bytes.subarray(i,i+8192));await clear();const result=await api('open',{files:[{path:file.name,contents:btoa(binary)}]});token=result.token;state=result;render();}catch(error){displayError(error);}finally{busy=false;choose.disabled=false;choose.value='';}});
 close.addEventListener('click',async()=>{if(dirty&&!confirm('清除后将丢弃未导出的修改，是否继续？'))return;try{await clear();status.textContent='临时存档已清除。';}catch(error){displayError(error);}});
 reset.addEventListener('click',()=>{if(!dirty||confirm('重置所有修改？'))render();});
 save.addEventListener('click',async()=>{if(busy||!state)return;const changes={fields:changed,collections:{}};let count=Object.keys(changed).length;for(const [cat,cs]of Object.entries(collections)){const rows=[...cs.rows].filter(([,values])=>Object.keys(values).length).map(([index,values])=>({index,values}));if(rows.length||cs.remove.size||cs.add.length){changes.collections[cat]={rows,remove:[...cs.remove],add:cs.add};count+=rows.length+cs.remove.size+cs.add.length;}}
  if(!confirm(`即将提交 ${count} 项修改，由 PHP 校验并生成存档。请确认已备份原始文件。`))return;busy=true;save.disabled=choose.disabled=selector.disabled=reset.disabled=close.disabled=true;
  try{status.textContent='正在生成存档…';const out=await api('export',{changes});const bytes=Uint8Array.from(atob(out.contents),x=>x.charCodeAt(0));const url=URL.createObjectURL(new Blob([bytes],{type:'application/octet-stream'}));const link=e('a');link.href=url;link.download=out.name;document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),60000);dirty=false;status.textContent='已请求浏览器下载 '+out.name+'。'+out.warning;}catch(error){displayError(error);}finally{busy=false;save.disabled=choose.disabled=selector.disabled=reset.disabled=close.disabled=false;}
 });
 window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
 try{const config=await api('config');registry=config.games;if(!registry[id])throw Error('此游戏尚未接入。');for(const [key,g]of Object.entries(registry))selector.add(new Option(g.title,key));selector.value=id;document.title=registry[id].title+' · PHP 存档修改器';const h1=document.querySelector('h1');if(h1)h1.textContent=registry[id].title+' 存档修改器';passwordLabel.hidden=!config.passwordRequired;status.textContent=`请上传 ${registry[id].filename}。`+(config.passwordRequired?'网站启用了访问密码。':'无需访问密码。');choose.setAttribute('aria-label','选择 '+registry[id].filename);}catch(error){choose.disabled=true;displayError(error);}
})();
