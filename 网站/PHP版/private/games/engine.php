<?php
declare(strict_types=1);
if(!defined('OOT_APP')){http_response_code(404);exit;}
require_once dirname(__DIR__).'/web.php';
final class GameBytes {
    public function __construct(public string $bytes,public bool $le=true){}
    public function bounds(int $o,int $n):void{if($o<0||$n<0||$o>strlen($this->bytes)-$n)throw new RuntimeException('存档字段超出文件范围。');}
    public function read(int $o,string $type): mixed {
        $n=match($type){'u8','bool'=>1,'u16'=>2,'u24'=>3,'u32','s32','f32'=>4,'str64'=>64,'wstr16'=>32,'wstr10'=>20,default=>throw new RuntimeException('字段类型不支持。')};$this->bounds($o,$n);$s=substr($this->bytes,$o,$n);
        return match($type){'u8'=>ord($s),'bool'=>(bool)ord($s),'u16'=>unpack($this->le?'v':'n',$s)[1],'u24'=>$this->le?ord($s[0])+(ord($s[1])<<8)+(ord($s[2])<<16):(ord($s[0])<<16)+(ord($s[1])<<8)+ord($s[2]),'u32'=>unpack($this->le?'V':'N',$s)[1],'s32'=>($u=unpack($this->le?'V':'N',$s)[1])>=0x80000000?$u-0x100000000:$u,'f32'=>unpack($this->le?'g':'G',$s)[1],'str64'=>explode("\0",$s,2)[0],'wstr16','wstr10'=>utf16($s)};
    }
    public function write(int $o,string $type,mixed $v):void{
        if($type==='str64'){$s=str_pad($v,64,"\0");}elseif(in_array($type,['wstr16','wstr10'],true)){$s=str_pad(encode16($v),$type==='wstr16'?32:20,"\0");}else{$s=match($type){'u8','bool'=>chr((int)$v),'u16'=>pack($this->le?'v':'n',$v),'u24'=>$this->le?substr(pack('V',$v),0,3):substr(pack('N',$v),1),'u32','s32'=>pack($this->le?'V':'N',$v),'f32'=>pack($this->le?'g':'G',$v),default=>throw new RuntimeException('不支持写入该类型。')};}
        $this->bounds($o,strlen($s));$this->bytes=substr_replace($this->bytes,$s,$o,strlen($s));
    }
}
function encode16(string $text):string {
    if(!preg_match('//u',$text))throw new RuntimeException('文本编码无效。');$out='';foreach(preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY) as $char){$a=array_values(unpack('C*',$char));$cp=match(count($a)){1=>$a[0],2=>(($a[0]&31)<<6)|($a[1]&63),3=>(($a[0]&15)<<12)|(($a[1]&63)<<6)|($a[2]&63),4=>(($a[0]&7)<<18)|(($a[1]&63)<<12)|(($a[2]&63)<<6)|($a[3]&63)};if($cp>65535){$cp-=65536;$out.=pack('vv',0xd800|($cp>>10),0xdc00|($cp&1023));}else $out.=pack('v',$cp);}return $out;
}
function checkedGameValue(mixed $v,array $f):mixed {
    if(($f['readonly']??false))throw new RuntimeException('此字段仅供查看。');$type=$f['type'];
    if($type==='bool'){if(!is_bool($v))throw new RuntimeException('布尔值无效。');return $v;}
    if(in_array($type,['str64','wstr16','wstr10'],true)){if(!is_string($v)||str_contains($v,"\0")||strlen($v)>63||(str_starts_with($type,'wstr')&&strlen(encode16($v))>($type==='wstr10'?18:30)))throw new RuntimeException('文本过长或无效。');return $v;}
    if((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||($type!=='f32'&&floor((float)$v)!=(float)$v)||$v<($f['min']??0)||$v>($f['max']??2147483647))throw new RuntimeException('字段值超出允许范围。');
    if(isset($f['options'])&&!in_array($v,array_column($f['options'],'value'),true))throw new RuntimeException('枚举选项不受支持。');
    return $type==='f32'?(float)$v:(int)$v;
}
abstract class GameAdapter {
    public GameBytes $b;public array $fields=[],$collections=[];public string $version='';public array $notes=[];
    public function __construct(string $bytes){$this->b=new GameBytes($bytes);$this->load();}
    abstract protected function load():void;
    protected function field(string $id,string $label,int $offset,string $type='u32',int|float $min=0,int|float $max=999999,array $extra=[]):void{$this->b->read($offset,$type);$this->fields[$id]=compact('id','label','offset','type','min','max')+$extra;}
    public function view():array {
        $fields=[];foreach($this->fields as $f){$v=$this->b->read($f['offset'],$f['type']);if(isset($f['bias']))$v+=$f['bias'];unset($f['offset'],$f['bias'],$f['unlockBytes'],$f['discovered']);$fields[]=$f+['value'=>$v];}
        return ['version'=>$this->version,'notes'=>$this->notes,'fields'=>$fields,'collections'=>$this->collectionView()];
    }
    protected function collectionView():array{return [];}
    protected function afterWrite(array $changes):void{}
    public function update(array $changes):string {
        if(!is_array($changes['fields']??null)||!is_array($changes['collections']??[]))throw new RuntimeException('修改格式无效。');
        foreach($changes['fields'] as $id=>$v){if(!isset($this->fields[$id]))throw new RuntimeException('未知字段，拒绝修改。');$f=$this->fields[$id];$v=checkedGameValue($v,$f);$this->b->write($f['offset'],$f['type'],isset($f['bias'])?$v-$f['bias']:$v);}
        $this->writeCollections($changes['collections']??[]);$this->afterWrite($changes);return $this->b->bytes;
    }
    protected function writeCollections(array $changes):void{if($changes)throw new RuntimeException('此游戏不支持物品列表修改。');}
}
