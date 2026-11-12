<?php
declare(strict_types=1);
if (!defined('OOT_APP')) { http_response_code(404); exit; }

final class SaveBlock {
    public array $fields = [];
    public function __construct(public string $bytes, public int $header) {
        if (strlen($bytes) < $header || $this->u32(0) !== 0x01020304 || $this->u32(4) !== 0x002D80EB || $this->u32(8) !== $header) throw new RuntimeException('此文件不符合受支持的 Switch 2 存档格式。');
        for ($o=40;$o<$header;$o+=8) { $h=$this->u32($o); if (!$h) continue; if(isset($this->fields[$h])) throw new RuntimeException('字段哈希重复。'); $this->fields[$h]=$o+4; }
    }
    public function range(int $o,int $len): void { if($o<0 || $len<0 || $o>strlen($this->bytes)-$len) throw new RuntimeException('存档数据范围无效。'); }
    public function u32(int $o): int { $this->range($o,4); return unpack('V',substr($this->bytes,$o,4))[1]; }
    public function at(int $h): int { if(!isset($this->fields[$h])) throw new RuntimeException('缺少必要字段：'.sprintf('0x%08X',$h)); return $this->fields[$h]; }
    public function int(int $h): int { $n=$this->u32($this->at($h)); return $n>=0x80000000?$n-0x100000000:$n; }
    public function block(int $h,int $len): int { $o=$this->u32($this->at($h)); if($o<$this->header) throw new RuntimeException('存档数据偏移无效。'); $this->range($o,$len); return $o; }
    public function text(int $o,int $len): string { $this->range($o,$len); return explode("\0",substr($this->bytes,$o,$len),2)[0]; }
    public function string(int $h,int $len=64): string { return $this->text($this->block($h,$len),$len); }
    public function set(int $h,int $v): void { $this->bytes=substr_replace($this->bytes,pack('V',$v),$this->at($h),4); }
    public function setString(int $h,string $value): void { if(strlen($value)>=64) throw new RuntimeException('物品 ID 过长。'); $this->bytes=substr_replace($this->bytes,str_pad($value,64,"\0"),$this->block($h,64),64); }
}
final class EditorCore {
    public array $d;
    public array $items=[],$addable=[],$removable=[],$flagTypes=[];
    public function __construct() {
        $this->d=require __DIR__.'/data.php';
        foreach($this->d['catalog']['inventory'] as $r) { [$h,$name,,$add]=$r; $this->items[$h]=$name; if($add) $this->addable[$h]=true; if($add&&!in_array($name,['Armor_Adult_Normal','Obj_Armor_Child_Normal'],true)) $this->removable[$h]=true; }
        foreach($this->d['catalog']['fields'] as $r) if(isset($r[2]) && $this->d['catalog']['types'][$r[1]]==='Bool') $this->flagTypes[$r[0]]=true;
    }
    public function slot(string $pbytes,string $cbytes): array {
        $p=new SaveBlock($pbytes,0x175D8);$c=new SaveBlock($cbytes,0x2A0);$k=$this->d['key'];$w=null;
        foreach($this->d['wallets'] as $v) if($v['id']===$p->string($k['wallet']))$w=$v;
        if(!$w||$p->int($k['limit'])!==$w['limit']||$p->int($w['stock'])!==1) throw new RuntimeException('钱包、持有标记与卢比上限不一致。');
        $rupees=$p->int($k['rupees']);if($rupees<0)throw new RuntimeException('卢比数量异常。');$c->at($k['previewRupees']);
        $capacities=[];$counts=[];
        foreach($this->d['capacityGroups'] as $g) {$n=$p->int($g['hash']);if($n<0||$n>9999)throw new RuntimeException('容量异常。');$capacities[$g['id']]=$n;}
        foreach($this->d['quantityFields'] as $i){$n=$p->int($i['stock']);if($n<0)throw new RuntimeException('物品数量异常。');$counts[$i['id']]=$n;}
        $bottles=[];foreach($this->d['bottleSlots'] as [$stock,$valid,$content])$bottles[]=['stock'=>$p->int($stock),'valid'=>$p->int($valid),'content'=>$p->u32($p->at($content))];
        $legacyMagic=in_array($p->int(0x12D63A97),[24,48],true);$vitals=[];
        foreach($this->d['vitalsFields'] as $f){$c->at($f['caption']);$scale=$legacyMagic&&in_array($f['id'],['magic','maxMagic'],true)?1:$f['scale'];$vitals[$f['id']]=$p->int($f['progress'])/$scale;}
        return compact('p','c','w','rupees','capacities','counts','bottles','vitals','legacyMagic');
    }
    public function option(string $bytes): array { $save=new SaveBlock($bytes,0x2A0);$language=$save->u32($save->at($this->d['voiceHash']));$this->language($language);return compact('save','language'); }
    public function language(mixed $language): void { if(!is_int($language)||!in_array($language,array_column($this->d['voiceLanguages'],0),true)) throw new RuntimeException('未知的语音语言。'); }
    public function integer(mixed $v,int $max,string $label): int { if(!is_numeric($v)||is_bool($v)||(!is_int($v)&&!is_float($v))||!is_finite((float)$v)||floor((float)$v)!=(float)$v||$v<0||$v>$max)throw new RuntimeException($label.'超出范围或不是整数。');return (int)$v; }
    public function removable(SaveBlock $p,int $h): string {
        if(!isset($this->removable[$h],$p->fields[$h])||$p->int($h)!==1)return '此处仅可移除受支持且已持有的单件物品。';
        foreach($this->d['equippedItemFields'] as $f){if(!isset($p->fields[$f]))return '无法检查装备字段。';if($p->string($f)===$this->items[$h])return '请先在少年和成年两个时期卸下此物品，再将其移除。';}return '';
    }
    public function acquisition(SaveBlock $p,int $h): array {
        $out=[];foreach($this->d['catalog']['registrations'] as [$stocks,$acq,$related])if(in_array($h,$stocks,true))foreach([$acq,$related] as $flag)if($flag)$out[$flag]=true;
        if(!$out)throw new RuntimeException('无法安全修改此物品的获取标记。');
        foreach($out as $flag=>$_)if(!isset($p->fields[$flag])||!in_array($p->int($flag),[0,1],true))throw new RuntimeException('无法安全修改此物品的获取标记。');return array_keys($out);
    }
    public function update(array $s,array $x): array {
        foreach(['wallet','rupees','vitals','amounts','bottles','additions','removals','clearAcquisitions','flags'] as $key)if(!array_key_exists($key,$x))throw new RuntimeException('缺少修改参数。');
        foreach(['amounts','vitals','additions','removals','clearAcquisitions','flags'] as $key)if(!is_array($x[$key]))throw new RuntimeException('修改参数无效。');
        $p=clone $s['p'];$c=clone $s['c'];$w=null;$k=$this->d['key'];
        foreach($this->d['wallets'] as $v)if($v['id']===$x['wallet'])$w=$v;
        if(!$w)throw new RuntimeException('未知的钱包。');$rupees=$this->integer($x['rupees'],$w['limit'],'卢比');
        if($w['id']!==$s['w']['id']){$p->setString($k['wallet'],$w['id']);$p->set($k['limit'],$w['limit']);foreach($this->d['wallets'] as $v)$p->set($v['stock'],(int)($v['id']===$w['id']));}
        $p->set($k['rupees'],$rupees);$c->set($k['previewRupees'],$rupees);
        $v=$x['vitals'];foreach(['hearts','maxHearts','magic','maxMagic'] as $id)if(!isset($v[$id])||(!is_int($v[$id])&&!is_float($v[$id]))||!is_finite((float)$v[$id]))throw new RuntimeException('生命或魔力值无效。');
        $mh=$this->integer($v['maxHearts'],20,'生命上限');if($mh<3||$v['hearts']<0||$v['hearts']>$mh||floor($v['hearts']*4)!=$v['hearts']*4)throw new RuntimeException('生命范围或步长无效。');
        $mm=$v['maxMagic'];$stages=[0,24,48];if(!in_array($mm,$stages)|| (in_array($s['vitals']['maxMagic'],$stages)&&$mm<$s['vitals']['maxMagic']))throw new RuntimeException('请选择当前魔力槽或更高等级。');
        $raw=(int)round($v['magic']*10);if($raw/10!=$v['magic']||$v['magic']<0||$v['magic']>$mm)throw new RuntimeException('魔力值范围或步长无效。');
        foreach($this->d['vitalsFields'] as $f){$value=$f['id']==='magic'?$raw:(int)($v[$f['id']]*$f['scale']);$p->set($f['progress'],$value);$c->set($f['caption'],$value);}
        if($mm>$s['vitals']['maxMagic']||$s['legacyMagic'])foreach($this->d['magicStages'] as $stage)if(isset($stage['blessing'])&&$stage['max']<=$mm)$p->set($stage['blessing'],1);
        $upgrades=$x['upgrades']??[];if(!is_array($upgrades))throw new RuntimeException('容量参数无效。');$pending=[];$groups=[];
        foreach($this->d['capacityGroups'] as $g){
            $id=$g['id'];$groups[$id]=$g;$current=$s['capacities'][$id];$tier=null;
            if(array_key_exists($id,$upgrades)){
                foreach($g['tiers'] as $i=>[$limit,$stock])if($limit===$upgrades[$id])$tier=$i;
                if($tier===null||!($upgrades[$id]>$current||($id==='seeds'&&$upgrades[$id]<$current&&$s['p']->int($g['tiers'][$tier][1])===1)))throw new RuntimeException('容量等级无效。');
            }else{
                $requested=-INF;foreach($this->d['supplies'] as $item)if(($item['group']??'')===$id&&isset($x['amounts'][$item['id']])&&$x['amounts'][$item['id']]>$s['counts'][$item['id']])$requested=max($requested,$x['amounts'][$item['id']]);
                if($requested>$current)foreach($g['tiers'] as $i=>[$limit])if($limit>=$requested){$tier=$i;break;}
            }
            if($tier!==null)$pending[]=[$g,$tier];
        }
        foreach($this->d['quantityFields'] as $item){$id=$item['id'];$g=$groups[$item['group']??'']??null;
            $max=$g?(array_key_exists($g['id'],$upgrades)?$upgrades[$g['id']]:max($s['capacities'][$g['id']],$g['tiers'][count($g['tiers'])-1][0])):$item['max'];
            if(!$g||!array_key_exists($g['id'],$upgrades))$max=max($max,$s['counts'][$id]);$p->set($item['stock'],$this->integer($x['amounts'][$id]??null,$max,$item['label']));
        }
        foreach($pending as [$g,$tier]){[$limit]=$g['tiers'][$tier];$p->set($g['hash'],$limit);if($limit>$s['capacities'][$g['id']])foreach(array_slice($g['tiers'],0,$tier+1) as [,$stock])$p->set($stock,1);if(isset($g['nameHash']))$p->setString($g['nameHash'],$g['actor'].'_'.['Small','Medium','Large'][$tier]);}
        $b=$this->integer($x['bottles'],count($this->d['bottleSlots']),'瓶子');$owned=[];$empty=[];
        foreach($s['bottles'] as $i=>$v){if($v['stock']===1)$owned[]=$i;elseif($v['stock']===0)$empty[]=$i;}
        if($b!==count($owned)){
            foreach($s['bottles'] as $v)if(!in_array($v['stock'],[0,1],true)||$v['stock']!==$v['valid'])throw new RuntimeException('瓶子持有字段不一致。');
            $increasing=$b>count($owned);$affected=$increasing?array_slice($empty,0,$b-count($owned)):array_slice($owned,$b);
            foreach($affected as $i){[$stock,$valid,$content]=$this->d['bottleSlots'][$i];$p->set($stock,(int)$increasing);$p->set($valid,(int)$increasing);$p->set($content,$this->d['emptyBottle']);}
        }
        $manual=[];foreach($x['flags'] as $pair){if(!is_array($pair)||count($pair)!==2||!is_int($pair[0])||!is_bool($pair[1]))throw new RuntimeException('标记修改格式无效。');$manual[$pair[0]]=$pair[1];}
        foreach(['additions','removals','clearAcquisitions'] as $kind)foreach($x[$kind] as $h)if(!is_int($h))throw new RuntimeException('物品哈希无效。');
        foreach($x['additions'] as $h){if(!isset($this->addable[$h])||$s['p']->int($h)!==0)throw new RuntimeException('此处无法添加此物品。');$p->set($h,1);}
        foreach($x['removals'] as $h){$issue=$this->removable($s['p'],$h);if($issue)throw new RuntimeException($issue);if(in_array($h,$x['additions'],true))throw new RuntimeException('不能同时添加和移除物品。');$p->set($h,0);}
        $resets=[];foreach($x['clearAcquisitions'] as $h){if(!in_array($h,$x['removals'],true))throw new RuntimeException('清除获取标记需要同时移除该物品。');foreach($this->acquisition($p,$h) as $f)$resets[$f]=true;}
        foreach($resets as $f=>$_){if(($manual[$f]??null)===true)throw new RuntimeException('进度标记与清除获取标记的操作冲突。');foreach($this->d['catalog']['registrations'] as [$stocks,$acq,$related])if($acq===$f||$related===$f)foreach($stocks as $h)if(isset($p->fields[$h])&&$p->int($h)>0)throw new RuntimeException('获取标记与仍持有的物品共用。');$p->set($f,0);}
        foreach($this->d['catalog']['registrations'] as [$stocks,$acq,$related,$name]){
            if(!isset($p->fields[$acq])||$p->int($acq)!==0)continue;$owned=false;foreach($stocks as $h)if(isset($p->fields[$h])&&$p->int($h)>0)$owned=true;if(!$owned)continue;
            foreach([$acq,$related] as $f)if($f&&isset($p->fields[$f])){if(($manual[$f]??null)===false)throw new RuntimeException('物品与其获取标记冲突。');$p->set($f,1);}
        }
        foreach($manual as $h=>$checked){$before=$s['p']->int($h);if(!isset($this->flagTypes[$h])||!in_array($before,[0,1],true))throw new RuntimeException('此处无法编辑此标记。');$p->set($h,(int)$checked);}
        $this->slot($p->bytes,$c->bytes);return [$p->bytes,$c->bytes];
    }
}
