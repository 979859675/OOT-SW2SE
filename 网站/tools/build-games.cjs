// Build-time extraction from trusted, attributed upstream sources. No Node.js in production.
const fs=require('fs'),path=require('path'),vm=require('vm');
const source=path.resolve(__dirname,'../塞尔达存档修改器'),target=path.resolve(__dirname,'../PHP版');
function context(){return vm.createContext({console,navigator:{userAgent:''},window:{addEventListener(){}},document:{addEventListener(){}},Locale:{add(lang,data){this[lang]=data;}},_:(s)=>s});}
function run(c,id,file){vm.runInContext(fs.readFileSync(path.join(source,id,file),'utf8'),c,{filename:file});}
function php(file,data){fs.writeFileSync(path.join(target,'private',file),"<?php\nif (!defined('OOT_APP')) { http_response_code(404); exit; }\nreturn json_decode(<<<'GAME_DATA'\n"+JSON.stringify(data)+"\nGAME_DATA, true, 512, JSON_THROW_ON_ERROR);\n");}
const t=context();for(const name of ['lib/murmurhash3js.min.js','zelda-totk.js','zelda-totk.variables.js','zelda-totk.class.pouch.js','zelda-totk.class.item.js','zelda-totk.class.equipment.js','zelda-totk.class.armor.js','locale/zelda-totk.locale.zh.js','locale/zelda-totk.locale.en.js'])run(t,'zelda-totk',name);
const totk=vm.runInContext('({versions:SavegameEditor.Constants.GAME_VERSIONS,structs:Pouch.Structs,available:{...Item.AVAILABILITY,...Equipment.AVAILABILITY,armors:Armor.AVAILABILITY},durability:Equipment.DEFAULT_DURABILITY,quantity:Item.MAXIMUM_QUANTITY,countable:Item.KEY_COUNTABLE,zh:Locale.zh})',t);
for(const struct of Object.values(totk.structs))for(const f of struct){f.hashNumber=t.murmurHash3.x86.hash32(f.hash);if(f.enumValues)f.options=f.enumValues.map(x=>({value:t.murmurHash3.x86.hash32(x),label:totk.zh[x]||x}));}
const words={Basic:'基本',Status:'基本数值',Coins:'金币',Name:'名字',Wins:'胜利',Loses:'失败','VR points':'VR 分数','StreetPass':'擦肩通信','Flow balls':'流程球',Onions:'洋葱','Silver onions':'银洋葱','Gold onions':'金洋葱',Rupees:'卢比',Mons:'怪','Max. hearts':'最大生命','Max. stamina':'最大精力',Materials:'材料',Characters:'角色',Items:'道具',Home:'主页'};
for(const [key,value] of Object.entries(t.Locale.en||{}))if(totk.zh[key])words[value]=totk.zh[key];
php('games/totk-data.php',totk);
const b=context();run(b,'zelda-botw','zelda-botw.js');run(b,'zelda-botw','zelda-botw.data.js');php('games/botw-data.php',{constants:b.SavegameEditor.Constants,hashes:b.SavegameEditor.Hashes,translations:b.BOTW_Data.Translations.map(g=>({...g,items:Object.fromEntries(Object.entries(g.items).map(([id,name])=>[id,totk.zh[id]||words[name]||name]))}))});
const a=context();run(a,'hyrule-warriors-age-of-calamity','hyrule-warriors-age-of-calamity.js');for(const m of [...a.SavegameEditor.Constants.MATERIALS,...a.SavegameEditor.Constants.CHARACTERS])m.label=words[m.label]||m.label;php('games/aoc-data.php',a.SavegameEditor.Constants);
const f=context();run(f,'final-fantasy-explorers','database.js');php('games/ffe-data.php',{items:f.ITEMS,materials:f.MATERIALS,atmaliths:f.ATMALITHS});
// Static original HTML retained without executable upstream scripts or event attributes.
const ids=['zelda-totk','zelda-botw','hyrule-warriors-age-of-calamity','mario-kart-7','rhythm-paradise-megamix','super-kirby-clash','kirbys-blowout-blast','pokemon-picross','final-fantasy-explorers'];
for(const id of ids){
 let html=fs.readFileSync(path.join(source,id,'index.html'),'utf8');
 html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/\s+on\w+\s*=\s*(?:"[^"]*"|'[^']*')/gi,'').replace(/<html[^>]*>/i,'<html lang="zh-CN">');
 html=html.replace(/\b(src|href)=(['"])(\.\/)?assets\//g,`$1=$2assets/games/${id}/assets/`).replace(/href=(['"])\.\/([^'"?]+\.css)(?:\?[^'"]*)?\1/g,`href=$1assets/games/${id}/$2$1`).replace(/href=(['"])\.\/favicon.png\1/g,`href=$1assets/games/${id}/favicon.png$1`);
 html=html.replace(/<link[^>]+href=["'](?:https?:|\.\.\/)[^>]*>/gi,'');
 if(id==='zelda-totk')html=html.replace(/(<[^>]+\bdata-translate="([^"]+)"[^>]*>)([^<]*)(<\/[^>]+>)/g,(all,start,key,text,end)=>start+(totk.zh[key]||text)+end);
 html=html.replace(/>([^<>]+)</g,(all,text)=>{const trimmed=text.trim(),translated=totk.zh[trimmed]||words[trimmed];return translated?'>'+text.replace(trimmed,translated)+'<':all;});
 // All templates include only trusted HTML, no raw PHP processing instructions.
 html=html.replace(/href=(["'])favicon.png\1/g,`href=$1assets/games/${id}/favicon.png$1`).replace(/src=(["'])favicon.png\1/g,`src=$1assets/games/${id}/favicon.png$1`);
 if(!/zelda-/.test(id))html=html.replace('</head>','<link rel="stylesheet" href="assets/games/common.css"></head>');
 html=html.replace(/<\?/g,'&lt;?').replace('</head>','<link rel="stylesheet" href="assets/games-shell.css?v=20261113-contrast"><script src="assets/multi-game.js" defer></script></head>');
 fs.writeFileSync(path.join(target,'private/views',id+'.php'),"<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>\n"+html);
 const dest=path.join(target,'assets/games',id);fs.mkdirSync(dest,{recursive:true});
 const walk=(dir,rel='')=>{for(const ent of fs.readdirSync(dir,{withFileTypes:true})){const r=path.join(rel,ent.name),full=path.join(dir,ent.name);if(ent.isDirectory())walk(full,r);else if(/\.(png|jpe?g|webp|svg|css)$/i.test(ent.name)){
 const out=path.join(dest,r);fs.mkdirSync(path.dirname(out),{recursive:true});if(r.endsWith('.css'))fs.writeFileSync(out,fs.readFileSync(full,'utf8').replace(/@import[^;]+;/g,''));else fs.copyFileSync(full,out);
 }}};walk(path.join(source,id));
}
fs.writeFileSync(path.join(target,'assets/games/common.css'),fs.readFileSync(path.join(source,'savegame-editor.css'),'utf8').replace(/@import[^;]+;/g,''));
fs.copyFileSync(path.join(source,'LICENSE'),path.join(target,'MARC-ROBLEDO-LICENSE.txt'));
console.log('Built '+ids.length+' original UI templates, styles, assets and private PHP data snapshots');
