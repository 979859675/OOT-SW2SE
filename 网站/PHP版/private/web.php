<?php
declare(strict_types=1);
if (!defined('OOT_APP')) { http_response_code(404); exit; }
require_once __DIR__.'/core.php';
function findBackup(array $files): array {
    $slots=[];
    foreach($files as $path=>$bytes)if(preg_match('~^(.*\/)?(slot_0[0-5])/progress\.sav$~D',$path,$m)){
        $base=$m[1]??'';$id=$m[2];if(isset($files[$base.$id.'/caption.sav']))$slots[]=['id'=>$id,'base'=>$base,'progress'=>$path,'caption'=>$base.$id.'/caption.sav','loose'=>false];
    }
    if(!$slots){foreach($files as $path=>$bytes)if(preg_match('~^([^/]+)/progress\.sav$~D',$path,$m)&&isset($files[$m[1].'/caption.sav']))return ['slots'=>[['id'=>$m[1],'progress'=>$path,'caption'=>$m[1].'/caption.sav','loose'=>true]],'option'=>isset($files[$m[1].'/option.sav'])?$m[1].'/option.sav':null,'manifest'=>null];throw new RuntimeException('请选择同一目录中的 progress.sav 和 caption.sav。');}
    $base=$slots[0]['base'];foreach($slots as $s)if($s['base']!==$base)throw new RuntimeException('请选择单个备份文件夹。');usort($slots,fn($a,$b)=>strcmp($a['id'],$b['id']));
    $manifest=str_ends_with($base,'files/')?substr($base,0,-6).'manifest.json':null;
    return ['slots'=>$slots,'option'=>isset($files[$base.'option.sav'])?$base.'option.sav':null,'manifest'=>$manifest&&isset($files[$manifest])?$manifest:null];
}
function previewImage(SaveBlock $c): ?string {
    $o=$c->block(0x63696A32,4);$n=$c->u32($o);if($n<4||$n>strlen($c->bytes)-$o-4||substr($c->bytes,$o+4,2)!=="\xff\xd8")return null;
    return 'data:image/jpeg;base64,'.base64_encode(substr($c->bytes,$o+4,$n));
}
function snapshot(EditorCore $core,array $session,mixed $index): array {
    if(!is_int($index)||!isset($session['backup']['slots'][$index]))throw new RuntimeException('存档槽位无效。');
    $active=$session['backup']['slots'][$index];$files=$session['files'];$s=$core->slot($files[$active['progress']],$files[$active['caption']]);$d=$core->d;$inventory=[];$flags=[];$capacities=[];
    foreach($d['catalog']['inventory'] as [$hash,$name,$category,$canAdd,$title])if(isset($s['p']->fields[$hash]))$inventory[]=compact('hash','name','category','canAdd','title')+['count'=>$s['p']->int($hash),'removalIssue'=>$core->removable($s['p'],$hash)];
    foreach($d['catalog']['fields'] as $f){[$hash]=$f;if(isset($core->flagTypes[$hash],$s['p']->fields[$hash])&&in_array($s['p']->int($hash),[0,1],true))$flags[]=['hash'=>$hash,'name'=>$f[2],'value'=>(bool)$s['p']->int($hash)];}
    foreach($d['capacityGroups'] as $g){$tiers=[];foreach($g['tiers'] as [$limit,$stock])if($limit>$s['capacities'][$g['id']]||($g['id']==='seeds'&&$limit<$s['capacities'][$g['id']]&&$s['p']->int($stock)===1))$tiers[]=$limit;$capacities[]=['id'=>$g['id'],'label'=>$g['label'],'tiers'=>$tiers];}
    $data=['slots'=>array_column($session['backup']['slots'],'id'),'index'=>$index,'rupees'=>$s['rupees'],'wallet'=>$s['w']['id'],'vitals'=>$s['vitals'],'capacities'=>$s['capacities'],'counts'=>$s['counts'],'bottles'=>count(array_filter($s['bottles'],fn($b)=>$b['stock']===1)),
        'wallets'=>array_map(fn($w)=>array_intersect_key($w,array_flip(['id','label','limit'])),$d['wallets']),
        'quantities'=>array_map(fn($i)=>array_intersect_key($i,array_flip(['id','label','max'])),$d['quantityFields']),
        'capacityGroups'=>$capacities,'magicStages'=>array_values(array_map(fn($v)=>array_intersect_key($v,array_flip(['max','label'])),array_filter($d['magicStages'],fn($v)=>$v['max']>=$s['vitals']['maxMagic']))),
        'voiceLanguages'=>$d['voiceLanguages'],'language'=>$session['language']??null,'inventory'=>$inventory,'flags'=>$flags,'preview'=>previewImage($s['c']),'legacyMagic'=>$s['legacyMagic'],'optionError'=>$session['optionError']??null];
    return [$s,$active,$data];
}
function decimal64(string $bytes,bool $signed=false): string {
    $a=array_values(unpack('C*',$bytes));$negative=$signed&&($a[7]&128);if($negative){$carry=1;foreach($a as &$b){$v=(255-$b)+$carry;$b=$v&255;$carry=$v>>8;}unset($b);}
    $decimal='0';foreach(array_reverse($a) as $b){$carry=$b;$out='';for($i=strlen($decimal)-1;$i>=0;$i--){$v=(int)$decimal[$i]*256+$carry;$out=($v%10).$out;$carry=intdiv($v,10);}while($carry){$out=($carry%10).$out;$carry=intdiv($carry,10);}$decimal=ltrim($out,'0')?:'0';}return ($negative?'-':'').$decimal;
}
function utf16(string $bytes): string {
    $units=array_values(unpack('v*',$bytes));$out='';for($i=0;$i<count($units);$i++){$c=$units[$i];if(!$c)break;if($c>=0xd800&&$c<=0xdbff&&isset($units[$i+1])&&$units[$i+1]>=0xdc00&&$units[$i+1]<=0xdfff){$c=0x10000+(($c-0xd800)<<10)+($units[++$i]-0xdc00);}elseif($c>=0xd800&&$c<=0xdfff)$c=0xfffd;
        if($c<128)$out.=chr($c);elseif($c<2048)$out.=chr(192|($c>>6)).chr(128|($c&63));elseif($c<65536)$out.=chr(224|($c>>12)).chr(128|(($c>>6)&63)).chr(128|($c&63));else $out.=chr(240|($c>>18)).chr(128|(($c>>12)&63)).chr(128|(($c>>6)&63)).chr(128|($c&63));}return $out;
}
function floatText(float $v): string {if(is_nan($v))return 'NaN';if(!is_finite($v))return $v<0?'-Infinity':'Infinity';return (string)(round($v,4)+0);}
function fieldValue(SaveBlock $p,int $h,string $type): string {
    try{$o=$p->at($h);$raw=$p->u32($o);$hex=fn($n)=>sprintf('0x%08X',$n);
        switch($type){
            case 'Bool':return $raw===0?'false':($raw===1?'true':$hex($raw));
            case 'Int':return (string)$p->int($h);case 'UInt':return (string)$raw;
            case 'Enum':case 'Unknown':return $hex($raw);
            case 'Float':return floatText(unpack('g',substr($p->bytes,$o,4))[1]);
            case 'String32':case 'String64':return $p->string($h,$type==='String32'?32:64)?:'—';
            case 'Int64':case 'UInt64':return decimal64(substr($p->bytes,$p->block($h,8),8),$type==='Int64');
            case 'Vector3':$base=$p->block($h,12);$out=[];for($i=0;$i<3;$i++)$out[]=floatText(unpack('g',substr($p->bytes,$base+$i*4,4))[1]);return implode(' / ',$out);
            case 'Binary':$size=$p->u32($p->block($h,4));$p->block($h,4+$size);return $size.' 字节，位置：'.$hex($raw);
        }
        if(in_array($type,['BoolArray','IntArray','UIntArray','FloatArray','EnumArray','UInt64Array','String64Array','WString16Array'],true)){
            $base=$p->block($h,4);$size=$p->u32($base);$stride=['UInt64Array'=>8,'String64Array'=>64,'WString16Array'=>32][$type]??4;$len=$type==='BoolArray'?(int)ceil($size/32)*4:$size*$stride;$p->block($h,4+$len);$out=[];
            for($i=0;$i<min($size,8);$i++){$o=$base+4+$i*$stride;$out[]=match($type){
                'BoolArray'=>(($p->u32($base+4+4*intdiv($i,32))>>($i%32))&1)?'true':'false',
                'IntArray'=>(string)(($n=$p->u32($o))>=0x80000000?$n-0x100000000:$n),
                'UIntArray'=>(string)$p->u32($o),'EnumArray'=>$hex($p->u32($o)),
                'FloatArray'=>floatText(unpack('g',substr($p->bytes,$o,4))[1]),
                'UInt64Array'=>decimal64(substr($p->bytes,$o,8)),
                'String64Array'=>json_encode($p->text($o,64),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
                'WString16Array'=>json_encode(utf16(substr($p->bytes,$o,32)),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)};}
            return $size.' · ['.implode(', ',$out).($size>count($out)?', …':'').']';
        }return '数据块位置：'.$hex($raw);
    }catch(Throwable){return '数据偏移无效：'.sprintf('0x%08X',$raw??0);}
}
function patchManifest(string $source,array $edits): string {
    $m=json_decode($source,true,512,JSON_THROW_ON_ERROR);
    if(($m['format']??null)!=='mac2jb.save-backup'||($m['version']??null)!==1||strtolower((string)($m['titleId']??''))!=='0400486024dec000'||!is_array($m['entries']??null))throw new RuntimeException('备份清单格式不支持。');
    foreach($edits as $e){$found=[];foreach($m['entries'] as $i=>$entry)if(($entry['path']??null)===$e['path'])$found[]=$i;
        if(count($found)!==1)throw new RuntimeException('备份清单不匹配。');$i=$found[0];if(($m['entries'][$i]['size']??null)!==strlen($e['before'])||($m['entries'][$i]['sha256']??null)!==hash('sha256',$e['before']))throw new RuntimeException('备份清单不匹配。');$m['entries'][$i]['sha256']=hash('sha256',$e['after']);}
    $json=json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);return preg_replace_callback('/^ +/m',fn($m)=>str_repeat(' ',intdiv(strlen($m[0]),2)),$json)."\n";
}
function storedZip(array $entries): string {
    $body='';$directory='';foreach($entries as $name=>$bytes){$crc=crc32($bytes);$len=strlen($bytes);$nl=strlen($name);$offset=strlen($body);
        $body.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0x21,$crc,$len,$len,$nl,0).$name.$bytes;
        $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0x21,$crc,$len,$len,$nl,0,0,0,0,0,$offset).$name;
    }return $body.$directory.pack('VvvvvVVv',0x06054b50,0,0,count($entries),count($entries),strlen($directory),strlen($body),0);
}
function exportPatch(EditorCore $core,array $session,int $index,array $input): array {
    [$s,$active]=snapshot($core,$session,$index);$x=$input['changes']??null;if(!is_array($x))throw new RuntimeException('修改数据无效。');
    foreach(['additions','removals','clearAcquisitions','flags'] as $key)if(!is_array($x[$key]??null)||!array_is_list($x[$key])||count($x[$key])>10000)throw new RuntimeException('修改列表无效。');
    $after=$core->update($s,$x);$edits=[];foreach(['progress.sav','caption.sav'] as $i=>$file){$edits[]=['path'=>($active['loose']?'':$active['id'].'/').$file,'before'=>$i===0?$s['p']->bytes:$s['c']->bytes,'after'=>$after[$i]];}
    if(isset($session['language'])&&($input['language']??null)!==$session['language']){
        $language=$input['language']??null;$core->language($language);$before=$session['files'][$session['backup']['option']];$opt=$core->option($before);$opt['save']->set($core->d['voiceHash'],$language);$edits[]=['path'=>'option.sav','before'=>$before,'after'=>$opt['save']->bytes];
    }
    $stamp=gmdate('Ymd_His');$uuid=random_bytes(16);$uuid[6]=chr((ord($uuid[6])&15)|64);$uuid[8]=chr((ord($uuid[8])&63)|128);$hex=bin2hex($uuid);$uuid=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    $name='Ocarina-of-Time-'.($active['loose']?'Save':$active['id']).'-Patch-'.$stamp.'_'.$uuid.'.zip';$entries=[];foreach($edits as $e)$entries[$stamp.'/'.$e['path']]=$e['after'];$warning='';
    if($session['backup']['manifest']){try{$entries['manifest.json']=patchManifest($session['files'][$session['backup']['manifest']],$edits);}catch(Throwable){$warning='备份清单不匹配，已跳过 manifest.json。';}}
    return ['name'=>$name,'contents'=>base64_encode(storedZip($entries)),'warning'=>$warning];
}
