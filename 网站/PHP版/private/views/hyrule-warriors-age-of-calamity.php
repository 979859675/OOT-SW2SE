<?php if (!defined('OOT_APP')) { http_response_code(404); exit; } ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<title>Savegame Editor &ndash; Hyrule Warriors</title>
	<meta http-equiv="content-Type" content="text/html; charset=UTF-8"/>
	<meta name="description" content="A savegame editor for Hyrule Warriors: Age of Calamity. It can edit values for your rupees and materials."/>
	<meta name="keywords" content="html5, savegame, save, editor, hack, exploit, wii u, hyrule warriors, materials, experience, weapons"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"/>
	<link rel="shortcut icon" href="assets/games/hyrule-warriors-age-of-calamity/favicon.png"/>
	
	
	
	
<link rel="stylesheet" href="assets/games/common.css"><link rel="stylesheet" href="assets/games-shell.css?v=20261113-contrast"><script src="assets/multi-game.js" defer></script></head>
<body>

<!-- HEADER -->
<div id="header">
	<div id="header-top">
		<div class="row wrapper">
			<h1 class="six columns text-left"><img src="assets/games/hyrule-warriors-age-of-calamity/favicon.png" /> Savegame Editor <small>for Hyrule Warriors</small></h1>
			<div class="six columns header-buttons text-right">
				by <a href="/">Marc Robledo</a>
				<i class="icon github"></i> <a href="https://github.com/marcrobledo/savegame-editors/tree/master/hyrule-warriors-age-of-calamity" target="_blank">See on GitHub</a>
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
	<!-- RUPEES -->
	<h3 class="green">卢比</h3>
	<div class="container">
		<div class="row" id="row-rupees">
			<div class="ten columns"><label for="number-rupees">卢比</label></div>
			<div class="two columns"><input id="number-rupees" type="text" class="fw text-right" /></div>
		</div>
	</div>

	<!-- MATERIALS -->
	<h3 class="orange">Materials: Fruits</h3>
	<div class="container">
		<div class="row" id="row-materials-fruits">
		</div>
	</div>
	<h3 class="orange">Materials: Mushrooms</h3>
	<div class="container">
		<div class="row" id="row-materials-mushrooms">
		</div>
	</div>
	<h3 class="orange">Materials: Vegetables</h3>
	<div class="container">
		<div class="row" id="row-materials-vegetables">
		</div>
	</div>
	<h3 class="orange">Materials: Meat</h3>
	<div class="container">
		<div class="row" id="row-materials-meat">
		</div>
	</div>
	<h3 class="orange">Materials: Ingredients</h3>
	<div class="container">
		<div class="row" id="row-materials-ingredients">
		</div>
	</div>
	<h3 class="orange">Materials: Fish</h3>
	<div class="container">
		<div class="row" id="row-materials-fish">
		</div>
	</div>
	<h3 class="orange">Materials: Insects</h3>
	<div class="container">
		<div class="row" id="row-materials-insects">
		</div>
	</div>
	<h3 class="orange">Materials: Minerals</h3>
	<div class="container">
		<div class="row" id="row-materials-minerals">
		</div>
	</div>
	<h3 class="orange">Materials: Monster Parts</h3>
	<div class="container">
		<div class="row" id="row-materials-monster">
		</div>
	</div>
	<h3 class="orange">Materials: Guardian Parts</h3>
	<div class="container">
		<div class="row" id="row-materials-guardian">
		</div>
	</div>
	<h3 class="blue">Materials: Trophies</h3>
	<div class="container">
		<div class="row" id="row-materials-trophies">
		</div>
	</div>
	<h3 class="red">Materials: Special</h3>
	<div class="container">
		<div class="row" id="row-materials-special">
		</div>
	</div>


	<!-- CHARACTERS -->
	<h3 class="red">Character Levels</h3>
	<div class="container">
		<div class="row" id="row-characters">
			<!-- <div class="three columns">&nbsp;</div> -->
			<!-- <div class="three columns">Damage</div>
			<div class="three columns">Level</div>
			<div class="three columns">履历</div> -->
		</div>
	</div>

	<!-- WEAPONS -->
	<!-- <h3 class="blue">武器</h3>
	<div class="container">
		<label for="select-weapon">Weapon slot</label>
		<span id="container-select-weapon"></span>
		<span class="bg-dark-gray round" style="padding: 3px 12px">Approximate power: <span id="span-weapon-power"></span></span>
		<hr/>
		<div class="row">
			<div class="three columns">Weapon type</div><div class="three columns" id="container-weapon-type"></div>
			<div class="three columns">Weapon ID</div><div class="three columns" id="container-weapon-id"></div>
			<div class="three columns">Base Power</div><div class="three columns" id="container-weapon-base-power"></div>
			<div class="three columns">Stars</div><div class="three columns" id="container-weapon-stars"></div>
		</div>
		<hr/>
		<div id="weapons-skills-container"></div>
	</div> -->
</div>

</body>
</html>
