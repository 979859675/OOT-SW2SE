<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>Savegame Editor &ndash; Kirby's Blowout Blast</title>
	<meta http-equiv="content-Type" content="text/html; charset=UTF-8"/>
	<meta name="description" content="A savegame editor for Kirby's Blowout Blast. It can unlock all amiibo puzzles."/>
	<meta name="keywords" content="html5, savegame, save, editor, hack, exploit, 3ds, kirbys, blowout, blast, kirby, unlock, amiibo, statues"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
	<link rel="shortcut icon" href="assets/games/kirbys-blowout-blast/favicon.png"/>
	
	
	
	
<link rel="stylesheet" href="assets/games/common.css"><link rel="stylesheet" href="assets/games-shell.css"><script src="assets/multi-game.js" defer></script></head>
<body>

<!-- HEADER -->
<div id="header">
	<div id="header-top">
		<div class="row wrapper">
			<h1 class="six columns text-left"><img src="assets/games/kirbys-blowout-blast/favicon.png" /> Savegame Editor <small>for Kirby's Blowout Blast</small></h1>
			<div class="six columns header-buttons text-right">
				by <a href="/">Marc Robledo</a>
				<i class="icon github"></i> <a href="https://github.com/marcrobledo/savegame-editors/tree/master/kirbys-blowout-blast" target="_blank">See on GitHub</a>
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
	<!-- AMIIBO -->
	<h3 class="orange">Statues</h3>
	<div class="container">
		You have unlocked <span id="span-amiibocount"></span><small>/5</small> (Robobot+amiibo) statues.
		<button>Unlock missing statues</button>
	</div>

</div>

</body>
</html>
