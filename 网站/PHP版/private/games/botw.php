<?php
if(!defined('OOT_APP')){http_response_code(404);exit;}
final class BotwAdapter extends GameAdapter {
    private array $data=[],$offsets=[],$ids=[],$translations=[],$rows=[];
    protected function load():void {
        $this->data=require __DIR__.'/botw-data.php';$n=strlen($this->b->bytes);$version=null;
        foreach([false,true] as $le){$this->b->le=$le;$header=$this->b->read(0,'u32');foreach($this->data['constants']['HEADER'] as $i=>$h)if($h===$header&&$n===$this->data['constants']['FILESIZE'][$i]&&$this->b->read(4,'u32')===0xffffffff){$version=$i;break 2;}}
        if($version===null)throw new RuntimeException('不是已验证版本的旷野之息 game_data.sav（Wii U / Switch）。');
        $this->version=$this->data['constants']['VERSION'][$version].' / '.($this->b->le?'Switch':'Wii U');$hashes=$this->data['hashes'];$start=12;
        for($i=0;$i<count($hashes);$i+=2)for($o=$start;$o+8<=$n;$o+=8)if($this->b->read($o,'u32')===$hashes[$i]){$this->offsets[$hashes[$i+1]]=$o+4;$start=$o+8;break;}
        foreach([['rupees','卢比','RUPEES',999999],['mons','怪','MONS',999999],['max-hearts','最大生命（四分之一颗心）','MAX_HEARTS',120],['koroks','克洛格计数','KOROK_SEED_COUNTER',900],['relic-gerudo','乌尔波扎之怒次数','RELIC_GERUDO',3],['relic-goron','达尔克尔的守护次数','RELIC_GORON',3],['relic-rito','力巴尔的勇猛次数','RELIC_RITO',3],['defeated-hinox','西诺克斯击败数','DEFEATED_HINOX_COUNTER',40],['defeated-talus','岩石巨人击败数','DEFEATED_TALUS_COUNTER',40],['defeated-molduga','莫尔德拉吉克击败数','DEFEATED_MOLDUGA_COUNTER',4]] as [$id,$label,$key,$max])if(isset($this->offsets[$key]))$this->field($id,$label,$this->offsets[$key],'u32',0,$max);
        $this->field('max-stamina','最大精力（每圈 1000）',$this->offsets['MAX_STAMINA'],'f32',1000,3000);
        foreach(['PLAYER_POSITION'=>'pos','HORSE_POSITION'=>'horse-pos'] as $key=>$prefix)if(isset($this->offsets[$key]))foreach(['x','y','z'] as $i=>$axis)$this->field($prefix.'-'.$axis,($prefix==='pos'?'林克':'马匹').'坐标 '.strtoupper($axis),$this->offsets[$key]+$i*8,'f32',-100000,100000);
        if(isset($this->offsets['MOTORCYCLE']))$this->field('motorcycle','大师摩托已获得',$this->offsets['MOTORCYCLE'],'u32',0,1);
        foreach($this->data['translations'] as $group)foreach($group['items'] as $id=>$name){$this->ids[$id]=$group['id'];$this->translations[$id]=$name;}
        $names=$this->offsets['ITEMS']??throw new RuntimeException('缺少物品字段。');$quantity=$this->offsets['ITEMS_QUANTITY']??throw new RuntimeException('缺少数量字段。');$columns=[];
        for($i=0;$i<420;$i++){$id=$this->text($names+$i*128,16);if(!$id)break;$cat=$this->ids[$id]??'other';$values=['id'=>$id,'quantity'=>$this->b->read($quantity+$i*8,'u32')];
            if(in_array($cat,['weapons','bows','shields'],true)){$col=$columns[$cat]??0;$columns[$cat]=$col+1;$key=['weapons'=>'WEAPON','bows'=>'BOW','shields'=>'SHIELD'][$cat];if(isset($this->offsets['FLAGS_'.$key],$this->offsets['FLAGSV_'.$key])){$values['modifier']=$this->b->read($this->offsets['FLAGS_'.$key]+$col*8,'u32');$values['modifierValue']=$this->b->read($this->offsets['FLAGSV_'.$key]+$col*8,'u32');$this->rows[$i]['modifierOffsets']=[$this->offsets['FLAGS_'.$key]+$col*8,$this->offsets['FLAGSV_'.$key]+$col*8];}}
            $this->rows[$i]=($this->rows[$i]??[])+['cat'=>$cat,'values'=>$values];
        }
        $this->notes=['支持原版已识别的 Wii U / Switch 存档。支持数值、坐标、已有物品替换/数量/耐久及装备词条；物品列表增删、马匹管理、大师编辑器和地图批量工具尚未迁移。','只输出 game_data.sav，请保留完整备份。'];
    }
    private function text(int $offset,int $chunks):string {$s='';for($i=0;$i<$chunks;$i++){$this->b->bounds($offset+$i*8,4);$s.=substr($this->b->bytes,$offset+$i*8,4);}return explode("\0",$s,2)[0];}
    private function writeText(int $offset,string $s):void{$s=str_pad($s,64,"\0");for($i=0;$i<16;$i++){ $this->b->bounds($offset+$i*8,4);$this->b->bytes=substr_replace($this->b->bytes,substr($s,$i*4,4),$offset+$i*8,4);}}
    protected function collectionView():array {
        $out=[];foreach($this->rows as $i=>$row){$cat=$row['cat'];if(!isset($out[$cat])){$options=[];foreach($this->ids as $id=>$c)if($c===$cat)$options[]=['value'=>$id,'label'=>$this->translations[$id]];$f=[['id'=>'id','label'=>'物品','type'=>'str64'],['id'=>'quantity','label'=>in_array($cat,['weapons','bows','shields'],true)?'耐久':'数量','type'=>'u32','min'=>0,'max'=>in_array($cat,['weapons','bows','shields'],true)?6553500:999]];if(isset($row['modifierOffsets'])){$f[]=['id'=>'modifier','label'=>'词条（原始值）','type'=>'u32','min'=>0,'max'=>4294967295];$f[]=['id'=>'modifierValue','label'=>'词条数值','type'=>'u32','min'=>0,'max'=>4294967295];}$out[$cat]=['id'=>$cat,'title'=>categoryLabel($cat),'capacity'=>420,'fields'=>$f,'rows'=>[],'available'=>$options,'canResize'=>false];}
            $out[$cat]['rows'][]=['index'=>$i,'title'=>$this->translations[$row['values']['id']]??$row['values']['id'],'values'=>$row['values']];
        }return array_values($out);
    }
    protected function writeCollections(array $changes):void {
        foreach($changes as $cat=>$change){if(!is_array($change)||!empty($change['remove'])||!empty($change['add']))throw new RuntimeException('此分类暂不支持增删。');$list=$change['rows']??[];if(!is_array($list)||count($list)>420)throw new RuntimeException('物品修改过多。');$seen=[];
            foreach($list as $r){$i=$r['index']??null;if(!is_int($i)||!isset($this->rows[$i])||$this->rows[$i]['cat']!==$cat||isset($seen[$i])||!is_array($r['values']??null))throw new RuntimeException('物品索引无效。');$seen[$i]=true;
                foreach($r['values'] as $p=>$v){if($p==='id'){$v=checkedGameValue($v,['type'=>'str64']);if(($this->ids[$v]??null)!==$cat)throw new RuntimeException('不能跨类别替换物品。');$this->writeText($this->offsets['ITEMS']+$i*128,$v);}elseif($p==='quantity'){$v=checkedGameValue($v,['type'=>'u32','min'=>0,'max'=>in_array($cat,['weapons','bows','shields'],true)?6553500:999]);$this->b->write($this->offsets['ITEMS_QUANTITY']+$i*8,'u32',$v);}elseif(in_array($p,['modifier','modifierValue'],true)&&isset($this->rows[$i]['modifierOffsets'])){$this->b->write($this->rows[$i]['modifierOffsets'][$p==='modifier'?0:1],'u32',checkedGameValue($v,['type'=>'u32','max'=>4294967295]));}else throw new RuntimeException('未知物品属性。');}
            }
        }
    }
}
