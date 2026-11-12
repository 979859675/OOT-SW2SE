<?php
if(!defined('OOT_APP')){http_response_code(404);exit;}
final class SimpleAdapter extends GameAdapter {
    public function __construct(string $bytes,private string $game){parent::__construct($bytes);}
    protected function load():void {
        $sizes=['mario-kart-7'=>20692,'rhythm-paradise-megamix'=>30040,'super-kirby-clash'=>54344,'kirbys-blowout-blast'=>1736,'pokemon-picross'=>14920,'final-fantasy-explorers'=>201816,'hyrule-warriors-age-of-calamity'=>1048576];
        if(strlen($this->b->bytes)!==$sizes[$this->game])throw new RuntimeException('文件大小不符合所选游戏支持的存档格式。');$this->version='上传源码支持的存档格式';
        $f=fn($id,$label,$o,$t,$max)=>$this->field($id,$label,$o,$t,0,$max);
        switch($this->game){
            case 'rhythm-paradise-megamix':foreach([['coins','金币',0x2dbc,'u16',9999],['flowballs','流程球',0x2dbe,'u16',255],['onions1','洋葱',0x3006,'u8',255],['onions2','银洋葱',0x3007,'u8',255],['onions3','金洋葱',0x3008,'u8',255]] as $r)$f(...$r);break;
            case 'mario-kart-7':
                $this->field('name','玩家名字',0x22,'wstr10');foreach([['coins','金币',0x4c28,'u24',99999],['streetpass','擦肩次数',0x4e24,'u24',99999],['wins','胜利次数',0x4e34,'u16',65535],['loses','失败次数',0x4e38,'u16',65535],['vrpoints','VR 分数',0x4e3c,'u16',65535]] as $r)$f(...$r);
                foreach(['cups'=>[0x4e3f,[0x3f]],'characters'=>[0x4e42,[255,1,255,1]],'karts'=>[0x4e48,[255,0x3f]],'tires'=>[0x4e4c,[0x7f]],'gliders'=>[0x4e50,[0x3f]]] as $id=>[$o,$bytes])$this->field('unlock-'.$id,'解锁'.['cups'=>'全部杯赛','characters'=>'全部角色','karts'=>'全部车身','tires'=>'全部轮胎','gliders'=>'全部滑翔翼'][$id],$o,'bool',0,1,['unlockBytes'=>$bytes,'oneway'=>true]);break;
            case 'super-kirby-clash':foreach([['applegems','宝石苹果',0x3ef0,'u32',99999],['boughtapplegems','已购宝石苹果',0x3f00,'u16',5000],['shards-red','火之碎片',0x3f20,'u16',9999],['shards-blue','水之碎片',0x3f24,'u16',9999],['shards-yellow','光之碎片',0x3f28,'u16',9999],['shards-rare','稀有碎片',0x3f2c,'u16',9999]] as $r)$f(...$r);foreach(['passwords'=>0x3a3c,'eshop'=>0x3a44] as $id=>$o)$this->field('mission-'.$id,'任务解锁：'.$id,$o,'u8',0,4,['options'=>[['value'=>0,'label'=>'隐藏'],['value'=>1,'label'=>'可见'],['value'=>2,'label'=>'新解锁'],['value'=>4,'label'=>'已解锁']]]);break;
            case 'kirbys-blowout-blast':for($i=0;$i<5;$i++)$this->field('statue-'.$i,'amiibo 雕像 '.($i+1),7+$i*2,'u8',0,1);$this->field('amiibo-enabled','amiibo 雕像功能',8,'u8',0,1);break;
            case 'pokemon-picross':$f('picrites','方块宝石',0xb40,'u16',9999);$f('boughtpicrites','已购方块宝石',0xb44,'u16',5000);$this->notes[]='已修正原源码商店标记表达式的运算符优先级问题，保留其他标记位。';break;
            case 'final-fantasy-explorers':$f('gil','金币',0x31424,'u24',9999999);$f('cp','CP',0x3142c,'u24',9999999);$data=require __DIR__.'/ffe-data.php';foreach(['items'=>0x23140,'materials'=>0x2ff98,'atmaliths'=>0x30128] as $cat=>$base)foreach($data[$cat] as [$i,,$name])$this->field($cat.'-'.$i,$name,$base+$i,'u8',0,99,['group'=>$cat]);break;
            case 'hyrule-warriors-age-of-calamity':$d=require __DIR__.'/aoc-data.php';$f('rupees','卢比',$d['RUPEES_OFFSET'],'u32',9999999);foreach($d['MATERIALS'] as $m)$this->field('material-'.$m['index'],$m['label'],$d['MATERIALS_OFFSET']+$m['index']*2,'u16',0,$m['customCap']??999,['discovered'=>$d['MATERIALS_DISCOVERED']+$m['index'],'group'=>$m['category']]);foreach($d['CHARACTERS'] as $c)$this->field('character-'.$c['index'],$c['label'].' · 等级',$d['CHARACTERS_OFFSET']+$c['index']*30,'u8',1,100,['bias'=>1,'group'=>'characters']);break;
        }
    }
    public function update(array $changes):string {
        $before=$this->b->bytes;$special=[];
        foreach($changes['fields']??[] as $id=>$v)if(isset($this->fields[$id]['unlockBytes'])){$f=$this->fields[$id];if(!is_bool($v))throw new RuntimeException('解锁值无效。');if(!$v)throw new RuntimeException('不支持反向锁定已解锁内容。');$special[$id]=$f;unset($changes['fields'][$id]);}
        parent::update($changes);
        foreach($special as $f)foreach($f['unlockBytes'] as $i=>$n)$this->b->write($f['offset']+$i,'u8',$n);
        foreach($changes['fields'] as $id=>$v){$f=$this->fields[$id];if(isset($f['discovered'])&&(new GameBytes($before))->read($f['offset'],$f['type'])===0&&$v>0)$this->b->write($f['discovered'],'u8',1);}
        if($this->game==='mario-kart-7')$this->b->write(20688,'u32',crc32(substr($this->b->bytes,0,20688)));
        if($this->game==='pokemon-picross'&&isset($changes['fields']['boughtpicrites'])){$flags=$this->b->read(0xb4c,'u8')&~1;$this->b->write(0xb4c,'u8',$flags|($changes['fields']['boughtpicrites']>=5000?1:0));}
        return $this->b->bytes;
    }
}
