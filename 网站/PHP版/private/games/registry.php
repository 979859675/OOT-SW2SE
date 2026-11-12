<?php
if(!defined('OOT_APP')){http_response_code(404);exit;}
require_once __DIR__.'/engine.php';require_once __DIR__.'/totk.php';require_once __DIR__.'/botw.php';require_once __DIR__.'/simple.php';
function gameRegistry():array {
    return [
        'oot'=>['title'=>'塞尔达传说：时之笛','filename'=>'progress.sav + caption.sav','href'=>'editor.php','description'=>'生命、魔力、物品、钥匙、获取与进度标记'],
        'zelda-totk'=>['title'=>'塞尔达传说：王国之泪','filename'=>'progress.sav','description'=>'数值、坐标、物品属性、装备耐久与词条、马匹属性；部分高级工具待迁移'],
        'zelda-botw'=>['title'=>'塞尔达传说：旷野之息','filename'=>'game_data.sav','description'=>'Wii U / Switch · 数值、坐标、已有物品、耐久与装备词条'],
        'hyrule-warriors-age-of-calamity'=>['title'=>'塞尔达无双：灾厄启示录','filename'=>'svdt','description'=>'卢比、素材数量、角色等级及素材发现标记'],
        'mario-kart-7'=>['title'=>'马力欧卡丁车 7','filename'=>'system0.dat','description'=>'名字、金币、战绩、VR、解锁与 CRC32'],
        'super-kirby-clash'=>['title'=>'超级卡比猎人队','filename'=>'savedata.dat','description'=>'宝石苹果、碎片与指定任务解锁'],
        'kirbys-blowout-blast'=>['title'=>'卡比的吸入大作战','filename'=>'savedata.dat','description'=>'amiibo 雕像状态'],
        'rhythm-paradise-megamix'=>['title'=>'节奏天国：最佳精选','filename'=>'savedataArc.txt','description'=>'金币、流程球、洋葱'],
        'pokemon-picross'=>['title'=>'宝可梦绘图方块','filename'=>'all.dat','description'=>'方块宝石与商店购买标记'],
        'final-fantasy-explorers'=>['title'=>'最终幻想：探索者','filename'=>'game0','description'=>'金币、CP、道具、素材与魔石'],
    ];
}
function makeGame(string $id,string $bytes):GameAdapter {if(!isset(gameRegistry()[$id])||$id==='oot')throw new RuntimeException('此游戏尚未接入该接口。');return match($id){'zelda-totk'=>new TotkAdapter($bytes),'zelda-botw'=>new BotwAdapter($bytes),default=>new SimpleAdapter($bytes,$id)};}
function categoryLabel(string $cat):string{return ['weapons'=>'武器','bows'=>'弓','shields'=>'盾牌','armors'=>'防具','clothes'=>'服装','arrows'=>'箭矢','materials'=>'材料','food'=>'料理','devices'=>'左纳乌装置','key'=>'重要物品','other'=>'其他物品','horses'=>'马匹'][$cat]??$cat;}
function propertyLabel(string $id):string {return ['id'=>'物品 ID','quantity'=>'数量','durability'=>'耐久','modifier'=>'词条','modifierValue'=>'词条数值','fuseId'=>'余料建造物品','fuseDurability'=>'余料耐久','extraDurability'=>'额外耐久','recordExtraDurability'=>'记录额外耐久','dyeColor'=>'染色','getOrder'=>'获取顺序','useOrder'=>'使用顺序','heartsHeal'=>'生命回复','effect'=>'效果','effectMultiplier'=>'效果等级','effectTime'=>'效果时间（秒）','price'=>'价格','recipe'=>'配方（只读）','name'=>'名字','bond'=>'亲密度','bondChecked'=>'亲密度已检查','statsStrength'=>'健壮','statsSpeed'=>'速度','statsStamina'=>'精力','statsPull'=>'挽力','mane'=>'鬃毛','saddles'=>'马鞍','reins'=>'缰绳','horseType'=>'马匹类型','colorType'=>'颜色类型','footType'=>'足部类型','roomId'=>'房间 ID'][$id]??$id;}
