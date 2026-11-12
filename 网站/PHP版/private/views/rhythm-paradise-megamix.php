<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>Savegame Editor &ndash; Rhythm Paradise/Heaven Megamix</title>
	<meta http-equiv="content-Type" content="text/html; charset=UTF-8"/>
	<meta name="description" content="A savegame editor for Rhythm Paradise/Heaven Megamix. It can edit values for your coins, flow balls and onions."/>
	<meta name="keywords" content="html5, savegame, save, editor, hack, exploit, 3ds, rhythm, paradise, heaven, megamix, flow, balls, onions, coins"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
	<link rel="shortcut icon" href="assets/games/rhythm-paradise-megamix/favicon.png"/>
	
	
	
	
<link rel="stylesheet" href="assets/games/common.css"><link rel="stylesheet" href="assets/games-shell.css"><script src="assets/multi-game.js" defer></script></head>
<body>

<!-- HEADER -->
<div id="header">
	<div id="header-top">
		<div class="row wrapper">
			<h1 class="six columns text-left"><img src="assets/games/rhythm-paradise-megamix/favicon.png" /> Savegame Editor <small>for Rhythm Paradise/Heaven Megamix</small></h1>
			<div class="six columns header-buttons text-right">
				by <a href="/">Marc Robledo</a>
				<i class="icon github"></i> <a href="https://github.com/marcrobledo/savegame-editors/tree/master/rhythm-paradise-megamix" target="_blank">See on GitHub</a>
				<i class="icon heart"></i> <a href="https://www.paypal.me/marcrobledo/5" target="_blank" rel="nofollow">Donate</a>
			</div>
		</div>
		<div class="hidden row wrapper" id="toolbar">
			<div class="twelve columns text-center">
				<button class="close"><i class="icon close"></i> 关闭文件</button>
				<button class="colored"><i class="icon accept"></i> 保存更改</button>
			</div>
		</div>
	</div>
</div>


<!-- THE EDITOR -->
<div id="the-editor" class="wrapper hidden">
	<!-- BASIC -->
	<h3 class="orange">基本</h3>
	<div class="container">
		<div class="row">
			<div class="three columns"><label for="number-coins">金币</label></div>
			<div class="three columns"><input id="number-coins" type="text" class="fw" /></div>
			<div class="three columns"><label for="number-onions1">洋葱</label></div>
			<div class="three columns"><input id="number-onions1" type="text" class="fw" /></div>
			<div class="three columns"><label for="number-flowballs">流程球</label></div>
			<div class="three columns"><input id="number-flowballs" type="text" class="fw" /></div>
			<div class="three columns"><label for="number-onions2">银洋葱</label></div>
			<div class="three columns"><input id="number-onions2" type="text" class="fw" /></div>
			<div class="three columns">&nbsp;</div>
			<div class="three columns">&nbsp;</div>
			<div class="three columns"><label for="number-onions3">金洋葱</label></div>
			<div class="three columns"><input id="number-onions3" type="text" class="fw" /></div>
		</div>
	</div>

</div>

</body>
</html>
