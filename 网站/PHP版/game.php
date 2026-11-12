<?php
declare(strict_types=1);
define('OOT_APP',true);require __DIR__.'/private/games/registry.php';
$id=$_GET['game']??'';if(!is_string($id)||!isset(gameRegistry()[$id])||$id==='oot'){http_response_code(404);exit('未找到此游戏。');}
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'");
require __DIR__.'/private/views/'.$id.'.php';
