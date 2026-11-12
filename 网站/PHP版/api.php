<?php
declare(strict_types=1);
define('OOT_APP',true);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function answer(array $data,int $status=200): never {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);exit;
}
function reject(string $message,int $status=400): never {throw new RuntimeException($message,$status);}
try {
    if(PHP_VERSION_ID<80200||PHP_INT_SIZE<8)reject('请在宝塔中选择 64 位 PHP 8.2 或更新版本。',503);
    $config=require __DIR__.'/private/config.php';$password=$config['password'];
    $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
    if($action==='config'&&$method==='GET')answer(['service'=>'oot-sw2se-web','passwordRequired'=>$password!=='','runtime'=>'php']);
    if(!in_array($action,['open','close','slot','fields','export'],true))reject('接口不存在。',404);
    if($method!=='POST')reject('请求方法不支持。',405);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin!==''){$parts=parse_url($origin);$host=$parts['host']??'';if(isset($parts['port']))$host.=':'.$parts['port'];if(!in_array($parts['scheme']??'',['http','https'],true)||strtolower($host)!==strtolower($_SERVER['HTTP_HOST']??''))reject('不允许跨站请求。',403);}
    if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')reject('不允许跨站请求。',403);
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))reject('仅接受 JSON 请求。',415);
    $max=18*1024*1024;if((int)($_SERVER['CONTENT_LENGTH']??0)>$max)reject('上传内容过大。',413);
    $dir=$config['storage_dir']?:rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).'/oot-sw2se-'.substr(hash('sha256',__DIR__),0,16);
    if(is_link($dir)||(!is_dir($dir)&&!@mkdir($dir,0700,true)))reject('无法创建临时目录，请检查 PHP 的 open_basedir 和目录写入权限。',503);
    $real=realpath($dir);$root=realpath($_SERVER['DOCUMENT_ROOT']??__DIR__);
    if(!$real||!is_writable($real)||($root&&($real===$root||str_starts_with($real,$root.DIRECTORY_SEPARATOR))))reject('存档临时目录必须可写且位于网站根目录之外。',503);
    // Lock rate updates, expiration cleanup and new session allocation across PHP workers.
    $lock=fopen($real.'/.lock','c');if(!$lock||!flock($lock,LOCK_EX))reject('无法锁定临时存储。',503);
    $now=time();foreach(glob($real.'/sess_*')?:[] as $file)if(filemtime($file)<$now-900)@unlink($file);
    $rateFile=$real.'/.rates';$rates=is_file($rateFile)?json_decode(file_get_contents($rateFile),true):[];if(!is_array($rates))$rates=[];
    foreach($rates as $id=>$r)if(($r['until']??0)<$now)unset($rates[$id]);
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$r=$rates[$ip]??['n'=>0,'until'=>$now+60];$r['n']++;if(count($rates)>5000&&!isset($rates[$ip]))reject('服务繁忙。',503);$rates[$ip]=$r;
    file_put_contents($rateFile,json_encode($rates));if($r['n']>90)reject('请求过于频繁，请稍后重试。',429);
    if($password!==''&&!hash_equals($password,$_SERVER['HTTP_X_SITE_PASSWORD']??''))reject('访问密码错误。',401);
    $raw=file_get_contents('php://input',false,null,0,$max+1);if($raw===false||strlen($raw)>$max)reject('上传内容过大。',413);
    try{$input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(JsonException){reject('请求 JSON 格式无效。');}if(!is_array($input))reject('请求格式无效。');unset($raw);
    require __DIR__.'/private/web.php';$core=new EditorCore();
    if($action==='open'){
        if(count(glob($real.'/sess_*')?:[])>=20)reject('存档会话已满，请稍后再试。',503);
        $rows=$input['files']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>16)reject('请选择不超过 16 个存档文件。');$files=[];$total=0;
        foreach($rows as $f){$path=$f['path']??null;$encoded=$f['contents']??null;
            if(!is_string($path)||strlen($path)>240||preg_match('~[\\\\\x00-\x1f]~',$path)||array_intersect(explode('/',$path),['','.','..'])||!preg_match('~(?:^|/)(?:progress\.sav|caption\.sav|option\.sav|manifest\.json)$~D',$path)||isset($files[$path]))reject('文件路径无效或重复。');
            if(!is_string($encoded)||strlen($encoded)>2796204||($bytes=base64_decode($encoded,true))===false||base64_encode($bytes)!==$encoded)reject('文件编码无效或文件超过 2 MiB。');
            $total+=strlen($bytes);if(strlen($bytes)>2*1024*1024||$total>12*1024*1024)reject('存档文件过大。',413);$files[$path]=$bytes;
        }
        $backup=findBackup($files);$session=['files'=>$files,'backup'=>$backup,'expires'=>$now+900];
        if($backup['option']){try{$session['language']=$core->option($files[$backup['option']])['language'];}catch(RuntimeException){$session['optionError']='option.sav 无法读取，已禁用语音编辑。';}}
        [,, $data]=snapshot($core,$session,0);$token=bin2hex(random_bytes(32));
    }else{
        $token=$_SERVER['HTTP_X_SESSION_TOKEN']??'';if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!is_file($real.'/sess_'.hash('sha256',$token)))reject('会话已过期，请重新上传存档。',401);
    }
    ini_set('session.use_cookies','0');ini_set('session.use_only_cookies','0');ini_set('session.use_strict_mode','0');ini_set('session.use_trans_sid','0');ini_set('session.gc_maxlifetime','900');ini_set('session.gc_probability','0');
    session_save_path($real);session_id(hash('sha256',$token));if(!session_start())reject('无法创建 PHP 会话。',503);
    if($action==='open'){$_SESSION=$session;flock($lock,LOCK_UN);fclose($lock);answer(['token'=>$token]+$data);}
    flock($lock,LOCK_UN);fclose($lock);
    if(!isset($_SESSION['expires'])||$_SESSION['expires']<$now){$_SESSION=[];session_destroy();reject('会话已过期，请重新上传存档。',401);}
    if($action==='close'){$_SESSION=[];session_destroy();answer(['ok'=>true]);}
    $_SESSION['expires']=$now+900;$session=$_SESSION;session_write_close();
    $index=$input['index']??null;[$s,$active,$data]=snapshot($core,$session,$index);
    if($action==='slot')answer($data);
    if($action==='fields'){
        $q=strtolower(substr((string)($input['search']??''),0,200));$rows=[];
        foreach($core->d['catalog']['fields'] as $f){[$h,$t]=$f;$name=$f[2]??'';$type=$core->d['catalog']['types'][$t];if(!isset($s['p']->fields[$h])||(!empty($input['named'])&&!$name)||!str_contains(strtolower($name.' '.dechex($h).' '.$type),$q))continue;$rows[]=[$h,$type,$name];}
        $page=max(0,min(1000,(int)($input['page']??0)));$total=count($rows);$rows=array_map(fn($r)=>['name'=>$r[2]?:'—','hash'=>'0x'.dechex($r[0]),'type'=>$r[1],'value'=>fieldValue($s['p'],$r[0],$r[1])],array_slice($rows,$page*80,80));answer(compact('total','rows'));
    }
    answer(exportPatch($core,$session,$index,$input));
}catch(Throwable $e){
    $code=$e->getCode();$status=is_int($code)&&$code>=400&&$code<600?$code:400;
    $message=$e instanceof RuntimeException?$e->getMessage():'无法处理请求，请检查文件、输入值及 PHP 配置。';answer(['error'=>$message],$status);
}
