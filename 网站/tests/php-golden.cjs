// Development-only golden vectors. PHP deployment does NOT need Node.js.
const fs=require('node:fs');
const path=require('node:path');
const {fixture}=require('../../tests/editor.test.cjs');
const core=require('../core.cjs');
const edit=core.editOptions(core.catalog);
const item=name=>core.catalog.inventory.find(x=>x[1]===name)[0];
const reg=h=>core.catalog.registrations.find(([stocks])=>stocks.includes(h));
const cases=[];
function add(name,f,extra={}) {
 const s=f.slot();const changes={wallet:s.w.id,rupees:s.rupees,vitals:s.vitals,amounts:s.counts,bottles:s.bottles.filter(x=>x.stock===1).length,additions:[],removals:[],clearAcquisitions:[],flags:[],...extra};
 const row={name,progress:Buffer.from(f.p).toString('base64'),caption:Buffer.from(f.c).toString('base64'),changes};
 try{row.expected=core.updateSlot(s,changes,edit).map(x=>Buffer.from(x).toString('base64'));}catch(e){row.error=true;}
 cases.push(row);
}
add('unchanged',fixture());
add('wallet-giant',fixture(),{wallet:'Item_Wallet_Large',rupees:999});
add('vitals-standard',fixture(),{vitals:{maxHearts:20,hearts:17.25,maxMagic:24,magic:12.3}});
add('vitals-double',fixture(),{vitals:{maxHearts:10,hearts:0.25,maxMagic:48,magic:48}});
const legacy=fixture();legacy.set(0x12D63A97,48);legacy.set(0x76B18200,24);add('legacy-magic',legacy);
for(const [id,g] of core.capacityGroups.map(x=>[x.id,x]))for(const [limit] of g.tiers)add('capacity-'+id+'-'+limit,fixture(),{upgrades:{[id]:limit}});
const supplies=fixture();add('automatic-capacities',supplies,{amounts:{...supplies.slot().counts,seeds:50,nuts:40,sticks:30,bombs:40,bombchu:35,arrows:50}});
add('bottle-add',fixture(),{bottles:5});
const bottles=fixture();for(const [stock,valid,content] of [[0xD53CE3A7,0xF39CE410,0x7D48DF76],[0xFCA29A04,0x000FF6EA,0x1EDD164A]]){bottles.set(stock,1);bottles.set(valid,1);bottles.set(content,0xEABA6067);}add('bottle-remove',bottles,{bottles:1});
add('add-item',fixture([]),{additions:[item('Item_Boomerang')]});
add('remove-preserve',fixture(),{removals:[item('Item_Boomerang')]});
add('remove-clear',fixture(),{removals:[item('Item_Boomerang')],clearAcquisitions:[item('Item_Boomerang')]});
const flag=reg(item('Item_Boomerang'))[1];
add('flag-edit',fixture(),{flags:[[flag,true]]});
add('flag-conflict',fixture(),{removals:[item('Item_Boomerang')],clearAcquisitions:[item('Item_Boomerang')],flags:[[flag,true]]});
add('flag-only-clear',fixture(),{clearAcquisitions:[item('Item_Boomerang')]});
add('add-remove-conflict',fixture(),{additions:[item('Item_Boomerang')],removals:[item('Item_Boomerang')]});
for(const field of [0xA95A3521,0xA1F7EE2B]){const f=fixture(['Weapon_Sword_Kokiri']);f.string(field,'Weapon_Sword_Kokiri');add('equipped-'+field,f,{removals:[item('Weapon_Sword_Kokiri')]});}
add('default-clothes',fixture(['Armor_Adult_Normal']),{removals:[item('Armor_Adult_Normal')]});
const bad=fixture();bad.set(flag,2);add('invalid-acquisition',bad,{removals:[item('Item_Boomerang')],clearAcquisitions:[item('Item_Boomerang')]});
add('rupees-overflow',fixture(),{rupees:201});
add('invalid-health',fixture(),{vitals:{maxHearts:20,hearts:1.1,maxMagic:0,magic:0}});
const decreasing=fixture();decreasing.set(0x12D63A97,480);add('magic-downgrade',decreasing,{vitals:{maxHearts:3,hearts:3,maxMagic:24,magic:0}});
const strings=fixture();add('quantity-string-rejected',strings,{amounts:{...strings.slot().counts,seeds:'1'}});
const fields=core.catalog.fields.filter(([,t,name])=>name&&['Bool','Int','Enum','String64'].includes(core.catalog.types[t])).slice(0,100).map(([h,t])=>({hash:h,type:core.catalog.types[t],value:core.fieldValue(strings.slot().p,h,core.catalog.types[t])}));
const voices=Array.from(core.voiceLanguages.keys()).map(language=>{
 const b=Buffer.alloc(0x2a4);b.writeUInt32LE(0x01020304,0);b.writeUInt32LE(0x002d80eb,4);b.writeUInt32LE(0x2a0,8);b.writeUInt32LE(0xa2dcdc9d,40);b.writeUInt32LE(language,44);return {language,bytes:b.toString('base64')};
});
const output=process.argv[2]||'.build/php-vectors.json';fs.mkdirSync(path.dirname(output),{recursive:true});fs.writeFileSync(output,JSON.stringify({cases,fields,voices}));console.log('Wrote '+cases.length+' PHP golden vectors to '+output);
