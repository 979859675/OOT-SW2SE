<?php
// Optional scheduled task: only runnable with PHP CLI, never through the website.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('OOT_APP',true);
$config=require __DIR__.'/config.php';
$dir=$config['storage_dir']?:rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).'/oot-sw2se-'.substr(hash('sha256',dirname(__DIR__)),0,16);
if(!is_dir($dir)||is_link($dir))exit;
$lock=fopen($dir.'/.lock','c');if(!$lock||!flock($lock,LOCK_EX))exit(1);
foreach(glob($dir.'/sess_*')?:[] as $file)if(filemtime($file)<time()-900)@unlink($file);
flock($lock,LOCK_UN);fclose($lock);
