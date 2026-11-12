<?php
if(!defined('OOT_APP')){http_response_code(404);exit;}
final class TotkAdapter extends GameAdapter {
    private array $table=[],$data=[],$arrays=[];
    protected function load():void {
        $n=strlen($this->b->bytes);if($n<2307552||$n>=4194304||$this->b->read(0,'u32')!==0x01020304)throw new RuntimeException('不是受支持的王国之泪 progress.sav。');
        $this->data=require __DIR__.'/totk-data.php';$found=false;
        for($o=40;$o<min($n-8,0x80000);$o+=8){$h=$this->b->read($o,'u32');if($h===0xa3db7114){$found=true;break;}if(!$h)continue;if(isset($this->table[$h]))throw new RuntimeException('王国之泪哈希表重复。');$this->table[$h]=$o+4;}
        if(!$found)throw new RuntimeException('无法识别王国之泪字段表。');
        foreach($this->data['versions'] as $v)if($v['fileSize']===$n&&$v['header']===$this->b->read(4,'u32')&&$v['metaDataStart']===$this->b->read(8,'u32'))$this->version=$v['version'];
        if(!$this->version)throw new RuntimeException('此王国之泪版本或模组尚未验证，拒绝修改。');
        foreach([
            ['rupees','卢比',0xa77921d7,0,999999],['max-hearts','最大生命（四分之一颗心）',0xfbe01da1,12,160],['pony-points','驿站点数',0x15ec5858,0,99999]
        ] as [$id,$label,$hash,$min,$max])$this->field($id,$label,$this->at($hash),'u32',$min,$max);
        $this->field('max-stamina','最大精力（每圈 1000）',$this->at(0xf9212c74),'f32',1000,3000);
        $this->field('max-battery','电池容量',$this->at(0xafd01d68),'f32',3000,48000);
        foreach(['weapons'=>0xd7a3f6ba,'bows'=>0xc61785c2,'shields'=>0x05271e7d] as $id=>$hash){$p=$this->b->read($this->at($hash),'u32');$this->b->bounds($p,8);if($this->b->read($p,'u32')<1)throw new RuntimeException('容量数组为空。');$this->field('pouch-'.$id,['weapons'=>'武器','bows'=>'弓','shields'=>'盾牌'][$id].'袋容量',$p+4,'u32',1,$id==='bows'?14:20);}
        $pos=$this->b->read($this->at(0xc884818d),'u32');foreach(['x','y','z'] as $i=>$axis)$this->field('pos-'.$axis,'坐标 '.strtoupper($axis),$pos+$i*4,'f32',-100000,100000);
        foreach($this->data['structs'] as $upper=>$struct){$cat=strtolower($upper);$fields=[];$capacity=null;
            foreach($struct as $f){if($f['type']==='UInt64Array')continue;$o=$this->b->read($this->at($f['hashNumber']),'u32');$this->b->bounds($o,4);$count=$this->b->read($o,'u32');$part=$f['arrayPartitionSize']??1;$type=match($f['type']){'String64Array'=>'str64','WString16Array'=>'wstr16','FloatArray'=>'f32','BoolArray'=>'bool','IntArray'=>'s32',default=>'u32'};$stride=match($type){'str64'=>64,'wstr16'=>32,'bool'=>0,default=>4};
                if($count>10000||$count%$part)throw new RuntimeException('物品数组长度异常。');$this->b->bounds($o+4,$type==='bool'?(int)ceil($count/32)*4:$count*$stride);
                $f+=['offset'=>$o+4,'count'=>$count,'typeSingle'=>$type,'stride'=>$stride,'part'=>$part];$fields[$f['propertyName']]=$f;
                if($f['propertyName']==='id')$capacity=$count;
            }
            if($capacity===null)continue;foreach($fields as $f)if($f['count']<$capacity*$f['part'])throw new RuntimeException('物品数组容量不一致。');$this->arrays[$cat]=['capacity'=>$capacity,'fields'=>$fields];
        }
        $this->notes=['当前迁移支持数值、坐标、已有物品属性、马匹属性，以及普通物品添加/移除。大师编辑器、地图标记、蓝图、批量完成度和武器经验工具尚未迁移；不会显示为可用。','重要物品的新增/移除暂不开放，避免遗漏获取和能力标记。输出为原始 progress.sav，不会同步 caption.sav。'];
    }
    private function at(int $h):int {if(!isset($this->table[$h]))throw new RuntimeException('缺少必要的王国之泪字段。');return $this->table[$h];}
    private function readCell(array $f,int $i):mixed {
        if($f['typeSingle']==='bool')return (bool)(($this->b->read($f['offset']+intdiv($i,32)*4,'u32')>>($i%32))&1);
        return $this->b->read($f['offset']+$i*$f['stride'],$f['typeSingle']);
    }
    private function writeCell(array $f,int $i,mixed $value):void {
        if($f['typeSingle']==='bool'){$o=$f['offset']+intdiv($i,32)*4;$n=$this->b->read($o,'u32');$mask=1<<($i%32);$this->b->write($o,'u32',$value?$n|$mask:$n&~$mask);}else $this->b->write($f['offset']+$i*$f['stride'],$f['typeSingle'],$value);
    }
    private function spec(string $cat,array $f):array {
        $prop=$f['propertyName'];$spec=['id'=>$prop,'label'=>propertyLabel($prop),'type'=>$f['typeSingle'],'min'=>$f['typeSingle']==='s32'?-2147483648:0,'max'=>$f['typeSingle']==='f32'?100:($f['typeSingle']==='u32'?4294967295:2147483647)];
        if($prop==='quantity'){$spec['min']=$cat==='key'?-1:0;$spec['max']=999999;}
        if($prop==='bond'){$spec['min']=0;$spec['max']=1;}
        if(in_array($prop,['statsStrength','statsSpeed','statsStamina','statsPull'],true)){$spec['min']=1;$spec['max']=5;}
        if(str_contains($prop,'Color')){$spec['min']=0;$spec['max']=255;}
        if(isset($f['options']))$spec['options']=$f['options'];
        if($f['part']>1)$spec['readonly']=true;
        return $spec;
    }
    protected function collectionView():array {
        $out=[];foreach($this->arrays as $cat=>$array){$rows=[];$specs=[];foreach($array['fields'] as $f)$specs[]=$this->spec($cat,$f);
            for($i=0;$i<$array['capacity'];$i++){$id=$this->readCell($array['fields']['id'],$i);if(!$id)break;$values=[];foreach($array['fields'] as $p=>$f)$values[$p]=$f['part']>1?array_map(fn($k)=>$this->readCell($f,$i*$f['part']+$k),range(0,$f['part']-1)):$this->readCell($f,$i);$rows[]=['index'=>$i,'title'=>$this->data['zh'][$id]??$id,'values'=>$values];}
            $options=[];foreach($this->data['available'][$cat]??[] as $id)$options[]=['value'=>$id,'label'=>$this->data['zh'][$id]??$id];
            $out[]=['id'=>$cat,'title'=>categoryLabel($cat),'capacity'=>$array['capacity'],'fields'=>$specs,'rows'=>$rows,'available'=>$options,'canResize'=>!in_array($cat,['key','horses'],true)];
        }return $out;
    }
    protected function writeCollections(array $changes):void {
        foreach($changes as $cat=>$change){if(!isset($this->arrays[$cat])||!is_array($change))throw new RuntimeException('未知物品分类。');$array=$this->arrays[$cat];$current=[];
            for($i=0;$i<$array['capacity'];$i++){if(!$this->readCell($array['fields']['id'],$i))break;$values=[];foreach($array['fields'] as $p=>$f)if($f['part']===1)$values[$p]=$this->readCell($f,$i);else $values[$p]=array_map(fn($k)=>$this->readCell($f,$i*$f['part']+$k),range(0,$f['part']-1));$current[]=$values;}
            $rows=$change['rows']??[];if(!is_array($rows)||count($rows)>$array['capacity'])throw new RuntimeException('物品修改数量异常。');$seen=[];
            foreach($rows as $row){$i=$row['index']??null;if(!is_int($i)||!isset($current[$i])||isset($seen[$i])||!is_array($row['values']??null))throw new RuntimeException('物品索引无效。');$seen[$i]=true;
                foreach($row['values'] as $p=>$v){if(!isset($array['fields'][$p]))throw new RuntimeException('未知物品属性。');$f=$array['fields'][$p];$spec=$this->spec($cat,$f);$v=checkedGameValue($v,$spec);
                    if($p==='id'&&$v!==$current[$i]['id']){if(in_array($cat,['key','horses'],true)||!in_array($v,$this->data['available'][$cat]??[],true))throw new RuntimeException('此分类不支持该物品替换。');}
                    $id=$p==='id'?$v:$current[$i]['id'];
                    if($p==='quantity'&&($cat!=='key'||in_array($id,$this->data['countable'],true))&&($v<0||$v>($this->data['quantity'][$id]??999)))throw new RuntimeException('此物品数量超出允许范围。');
                    if($p==='quantity'&&$cat==='key'&&!in_array($id,$this->data['countable'],true))throw new RuntimeException('此重要物品不可修改数量。');
                    $current[$i][$p]=$v;
                }
            }
            $remove=$change['remove']??[];$add=$change['add']??[];if(!is_array($remove)||!is_array($add)||count($add)>$array['capacity'])throw new RuntimeException('物品增删参数无效。');
            if(($remove||$add)&&in_array($cat,['key','horses'],true))throw new RuntimeException('重要物品和马匹暂不支持增删。');
            foreach($remove as $i)if(!is_int($i)||!isset($current[$i]))throw new RuntimeException('移除索引无效。');$next=[];foreach($current as $i=>$row)if(!in_array($i,$remove,true))$next[]=$row;
            foreach($add as $id){if(!is_string($id)||!in_array($id,$this->data['available'][$cat]??[],true))throw new RuntimeException('添加物品不受支持。');$row=[];foreach($array['fields'] as $p=>$f){$row[$p]=match($p){'id'=>$id,'quantity'=>1,'durability'=>$this->data['durability'][$id]??70,'modifier','dyeColor','effect'=>(int)hexdec(hash('murmur3a','None')),'heartsHeal'=>4,'price'=>1,'recordExtraDurability'=>-1,default=>in_array($f['typeSingle'],['str64','wstr16'],true)?'':($f['typeSingle']==='bool'?false:0)};if($f['part']>1)$row[$p]=array_fill(0,$f['part'],'');}$next[]=$row;}
            if($cat==='arrows'&&$add){$hash=(int)hexdec(hash('murmur3a','Pouch.Arrow.EquipIndex'));$p=$this->b->read($this->at($hash),'u32');$this->b->bounds($p,8);if($this->b->read($p,'u32')<1)throw new RuntimeException('箭矢装备数组无效。');if($this->b->read($p+4,'s32')===-1)$this->b->write($p+4,'s32',0);}
            if(count($next)>$array['capacity']||count($next)<1)throw new RuntimeException('物品列表超出容量，或试图移除最后一个条目。');
            // Write all properties when compacting; otherwise write only explicitly changed cells.
            if($remove||$add){foreach($next as $i=>$row)foreach($array['fields'] as $p=>$f)for($k=0;$k<$f['part'];$k++)$this->writeCell($f,$i*$f['part']+$k,$f['part']>1?$row[$p][$k]:$row[$p]);for($i=count($next);$i<$array['capacity'];$i++)$this->writeCell($array['fields']['id'],$i,'');}
            else foreach($rows as $row)foreach($row['values'] as $p=>$v)$this->writeCell($array['fields'][$p],$row['index'],$current[$row['index']][$p]);
        }
    }
}
