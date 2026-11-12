<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>Savegame Editor &ndash; Pokémon Picross</title>
	<meta http-equiv="content-Type" content="text/html; charset=UTF-8"/>
	<meta name="description" content="A savegame editor for Pokémon Picross. It can let you get free picrites."/>
	<meta name="keywords" content="html5, savegame, save, editor, hack, exploit, 3ds, pokemon, picross, free, picrites"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
	<link rel="shortcut icon" href="assets/games/pokemon-picross/favicon.png"/>
	
	
	
	
<link rel="stylesheet" href="assets/games/common.css"><link rel="stylesheet" href="assets/games-shell.css?v=20261113-contrast"><script src="assets/multi-game.js" defer></script></head>
<body>

<!-- HEADER -->
<div id="header">
	<div id="header-top">
		<div class="row wrapper">
			<h1 class="six columns text-left"><img src="assets/games/pokemon-picross/favicon.png" /> Savegame Editor <small>for Pokémon Picross</small></h1>
			<div class="six columns header-buttons text-right">
				by <a href="/">Marc Robledo</a>
				<i class="icon github"></i> <a href="https://github.com/marcrobledo/savegame-editors/tree/master/pokemon-picross" target="_blank">See on GitHub</a>
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
	<!-- PICRITES -->
	<h3 class="orange">Picrites</h3>
	<div class="container">
		<div class="row">
			<div class="six columns center"><label for="number-picrites">Picrites</label></div>
			<div class="four columns center"><input id="number-picrites" type="text" /></div>
		</div>
		<div class="row">
			<div class="six columns center"><label for="number-boughtpicrites">Bought picrites</label><br/><small>(Note: if you buy 5000 picrites, you have access to unlimited picrites in-game)</small></div>
			<div class="four columns center"><input id="number-boughtpicrites" type="text" /></div>
		</div>
	</div>
</div>

</body>
</html>
