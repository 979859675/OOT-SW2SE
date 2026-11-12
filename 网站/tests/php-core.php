<?php
declare(strict_types=1);
define('OOT_APP',true);
require __DIR__.'/../PHP版/private/web.php';
function ok(bool $condition,string $message): void {if(!$condition)throw new RuntimeException('FAIL: '.$message);}
$core=new EditorCore();$vectors=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);$passed=0;
foreach($vectors['cases'] as $r){
    $s=$core->slot(base64_decode($r['progress']),base64_decode($r['caption']));$error=false;
    try{$result=$core->update($s,$r['changes']);}catch(RuntimeException $e){$error=true;}
    if(!empty($r['error'])){ok($error,$r['name'].' should reject');}else{ok(!$error,$r['name'].' unexpected error');ok(array_map('base64_encode',$result)===$r['expected'],$r['name'].' output bytes differ');}
    ok(base64_encode($s['p']->bytes)===$r['progress'],$r['name'].' source mutated');$passed++;
}
$r=$vectors['cases'][0];$p=new SaveBlock(base64_decode($r['progress']),0x175D8);
foreach($vectors['fields'] as $f)ok(fieldValue($p,$f['hash'],$f['type'])===$f['value'],'field '.$f['hash']);
foreach($vectors['voices'] as $v){$o=$core->option(base64_decode($v['bytes']));ok($o['language']===$v['language'],'voice language');$o['save']->set($core->d['voiceHash'],$vectors['voices'][0]['language']);ok($core->option($o['save']->bytes)['language']===$vectors['voices'][0]['language'],'voice write');}
$files=['Backup/files/slot_00/progress.sav'=>base64_decode($r['progress']),'Backup/files/slot_00/caption.sav'=>base64_decode($r['caption'])];$backup=findBackup($files);ok($backup['slots'][0]['id']==='slot_00','slot discovery');
$session=['files'=>$files,'backup'=>$backup];[,, $view]=snapshot($core,$session,0);ok($view['rupees']===$r['changes']['rupees'],'snapshot rupees');ok(count($view['inventory'])>50,'inventory snapshot');
$edits=[];foreach(['progress.sav','caption.sav'] as $i=>$file)$edits[]=['path'=>'slot_00/'.$file,'before'=>$files['Backup/files/slot_00/'.$file],'after'=>base64_decode($r['expected'][$i])];
$manifest=['format'=>'mac2jb.save-backup','version'=>1,'titleId'=>'0400486024dec000','entries'=>array_map(fn($e)=>['path'=>$e['path'],'size'=>strlen($e['before']),'sha256'=>hash('sha256',$e['before'])],$edits)];
$patched=json_decode(patchManifest(json_encode($manifest),$edits),true);foreach($edits as $i=>$e)ok($patched['entries'][$i]['sha256']===hash('sha256',$e['after']),'manifest hash');
$files['Backup/manifest.json']=json_encode($manifest);$session=['files'=>$files,'backup'=>findBackup($files)];$patch=exportPatch($core,$session,0,['changes'=>$r['changes'],'language'=>null]);ok($patch['warning']==='','valid manifest skipped');
file_put_contents($argv[2],base64_decode($patch['contents']));
ok(decimal64(str_repeat("\xff",8))==='18446744073709551615','unsigned 64-bit');ok(decimal64(str_repeat("\xff",8),true)==='-1','signed 64-bit');ok(decimal64("\0\0\0\0\0\0\0\x80",true)==='-9223372036854775808','signed min');
try{new SaveBlock('bad',0x175D8);ok(false,'invalid save accepted');}catch(RuntimeException){}
echo "PASS: $passed golden edit vectors, field reads, 9 voice languages, manifest, multi-slot snapshot and ZIP export\n";
