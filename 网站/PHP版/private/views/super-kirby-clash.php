<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>Savegame Editor &ndash; Super Kirby Clash</title>
	<meta http-equiv="content-Type" content="text/html; charset=UTF-8"/>
	<meta name="description" content="A savegame editor for Super Kirby Clash. It can edit values for your apple and materials."/>
	<meta name="keywords" content="html5, savegame, save, editor, hack, exploit, switch, super kirby clash, free, apples"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
	<link rel="shortcut icon" href="assets/games/super-kirby-clash/favicon.png"/>
	
	
	
	
<link rel="stylesheet" href="assets/games/common.css"><link rel="stylesheet" href="assets/games-shell.css?v=20261113-contrast"><script src="assets/multi-game.js" defer></script></head>
<body>

<!-- HEADER -->
<div id="header">
	<div id="header-top">
		<div class="row wrapper">
			<h1 class="six columns text-left"><img src="assets/games/super-kirby-clash/favicon.png" /> Savegame Editor <small>for Super Kirby Clash</small></h1>
			<div class="six columns header-buttons text-right">
				by <a href="/">Marc Robledo</a>
				<i class="icon github"></i> <a href="https://github.com/marcrobledo/savegame-editors/tree/master/super-kirby-clash" target="_blank">See on GitHub</a>
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
	<!-- PROFILE -->
	<!-- <h3 class="orange">Profile</h3>
	<div class="container">
		<div class="row">
			<div class="nine columns"><label for="input-name">Message</label></div>
			<div class="three columns"><input id="input-name" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="input-time">Played time <small>(in seconds)</small></label></div>
			<div class="three columns"><input id="input-time" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="input-missions">Completed missions</label></div>
			<div class="three columns"><input id="input-missions" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="input-encounters">Encounters</label></div>
			<div class="three columns"><input id="input-encounters" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="input-multiplayerbattles">Multiplayer battles</label></div>
			<div class="three columns"><input id="input-multiplayerbattles" type="text" class="fw" /></div>
		</div>
	</div> -->

	<!-- APPLE GEMS -->
	<h3 class="red">Apple gems</h3>
	<div class="container">
		<div class="row">
			<div class="nine columns"><label for="number-applegems">Apple gems</label></div>
			<div class="three columns"><input id="number-applegems" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="number-boughtapplegems">Bought apple gems <small>(affects tree)</small></label></div>
			<div class="three columns"><input id="number-boughtapplegems" type="text" class="fw" /></div>
		</div>
	</div>

	<!-- CRYSTAL SHARDS -->
	<h3 class="green">Crystal shards</h3>
	<div class="container">
		<div class="row">
			<div class="nine columns"><label for="number-shards-red">Fire shards</label></div>
			<div class="three columns"><input id="number-shards-red" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="number-shards-blue">Water shards</label></div>
			<div class="three columns"><input id="number-shards-blue" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="number-shards-yellow">Light shards</label></div>
			<div class="three columns"><input id="number-shards-yellow" type="text" class="fw" /></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="number-shards-rare">Rare shards</label></div>
			<div class="three columns"><input id="number-shards-rare" type="text" class="fw" /></div>
		</div>
	</div>

	<!-- ONLINE MISSIONS -->
	<h3 class="blue">Online only heroic missions</h3>
	<div class="container">
		<div class="row">
			<div class="nine columns"><label for="button-mission-passwords">Use a Password</label></div>
			<div class="three columns"><button id="button-mission-passwords">解锁</button></div>
		</div>
		<div class="row">
			<div class="nine columns"><label for="button-mission-eshop">Access Nintendo eShop from Other Games</label></div>
			<div class="three columns"><button id="button-mission-eshop">解锁</button></div>
		</div>
	</div>

</div>

</body>
</html>
