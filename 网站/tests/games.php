<?php
declare(strict_types=1);
define('OOT_APP',true);
require dirname(__DIR__).'/PHP版/private/games/registry.php';
function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(gameRegistry() as $id=>$g){
 if($id==='oot')continue;
 $bytes=file_get_contents(dirname(__DIR__).'/塞尔达存档修改器/'.$id.'/'.$g['filename']);
 $a=makeGame($id,$bytes);$view=$a->view();
 verify(count($view['fields'])>0,$id.' missing fields');
 $noop=$a->update(['fields'=>[],'collections'=>[]]);verify($noop===$bytes,$id.' no-op changes original');
 $field=$view['fields'][0];$value=$field['type']==='wstr10'?'123':min(123,$field['max']);
 $result=makeGame($id,$bytes)->update(['fields'=>[$field['id']=>$value],'collections'=>[]]);
 $parsed=makeGame($id,$result)->view();verify($parsed['fields'][0]['value']===$value,$id.' round trip');
 foreach([['unknown'=>1],[$field['id']=>null]] as $bad){$rejected=false;try{makeGame($id,$bytes)->update(['fields'=>$bad]);}catch(RuntimeException $e){$rejected=true;}verify($rejected,$id.' invalid input accepted');}
 echo $id." parse / no-op / edit / reject OK\n";
}
