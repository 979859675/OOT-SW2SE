<?php
declare(strict_types=1);
define('OOT_APP',true);
require __DIR__.'/private/games/registry.php';
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'none'; object-src 'none'; base-uri 'none'");
function esc(string $s):string{return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>塞尔达与更多游戏 · 存档工坊</title><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/games-home.css"></head><body>
<header><div class="eyebrow">SAVE EDITORS / PHP EDITION</div><h1>游戏存档<span>修改工坊</span></h1><p>选择游戏，打开备份，再继续你的冒险。</p><div class="badge">默认简体中文 · PHP 后端处理 · 无需 Node.js</div></header>
<main><section><h2>选择游戏</h2><p class="muted">文件将上传到 PHP 服务器并临时保存。不同游戏使用独立解析器；请始终备份原始存档。新增游戏沿用原修改器的界面资源，已迁移范围见卡片及编辑页说明。</p><div class="game-cards">
<?php foreach(gameRegistry() as $id=>$game): ?><a class="game-card" href="<?=esc($game['href']??('game.php?game='.$id))?>"><?php if($id!=='oot'):?><img src="assets/games/<?=esc($id)?>/thumb.jpg" alt="" loading="lazy"><?php endif;?><div><h3><?=esc($game['title'])?></h3><p><?=esc($game['description'])?></p><small>存档：<?=esc($game['filename'])?></small><b>打开修改器 →</b></div></a><?php endforeach;?>
</div></section><section><h2>尚未完成迁移</h2><p class="muted">其余上传源码中的游戏（如塞尔达无双 Wii U、任天堂明星大乱斗特别版、宝可梦消消乐等）目前不提供编辑入口，避免未经验证的修改。新增游戏页中未迁移的高级工具不会调用原版前端算法冒充 PHP 功能。</p></section></main>
<footer>时之笛：Traiver 及上游贡献者 · 新增修改器：Marc Robledo 及原项目贡献者<br>MIT License · 本工具与 Nintendo 无关联。</footer></body></html>
