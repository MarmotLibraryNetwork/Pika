{* This page was originally created by CJ O'Hara *}
<!DOCTYPE html>
<html lang="en">
<meta charset="utf-8">
<head>
<title>Maintenance in Progress | {$libraryName}</title>
<style>
	{literal}

html { overflow:hidden; }
/*body { font: 60px 'SilkscreenNormal', Arial, sans-serif; letter-spacing:0; background:#467E99FF; color:#fff; }*/
/*body { font: 60px 'SilkscreenNormal', Arial, sans-serif; letter-spacing:0; background:#4DA9E2; color:#fff; }*/
body { font: 60px 'SilkscreenNormal', Arial, sans-serif; letter-spacing:0; background:#5891AA; color:#fff; }

#container {
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
}

h1, h2 { margin:0; text-shadow:0 5px 0 rgba(0,0,0,.5); }
h1 { font-size:1em; }
h2 { font-size:.5em; }
a { color:#fff; }
h3 { font-size:.25em; margin:1em 50px; }
h3, h3 a { color:#6b778d; }
h3 img { margin:0 3px; }
h4 { font-size:.30em; margin:1em 50px; }
h4, h4 a { color:#ffffff; }

#title { position:absolute; top:50%; width:100%; height:322px; margin-top:-180px; text-align:center; z-index:10; }
.cloud { position:absolute; display:block; }
/*.puff { position:absolute; display:block; width:15px; height:15px; background:white; opacity:.05; filter:alpha(opacity=5); }*/
/*.puff { position:absolute; display:block; width:25px; height:25px; background:white; opacity:.05; filter:alpha(opacity=5); }*/
	.puff { position:absolute; display:block; width:84px; height:59px; background:white; opacity:.05; filter:alpha(opacity=5); }

</style>
<script>
function randomInt(min, max) {
	return Math.floor(Math.random() * (max - min + 1)) + min
}

function randomChoice(items) {
	return items[randomInt(0, items.length-1)]
}

var PIXEL_SIZE = 25

function makeCloud() {
	var w = 8,
		h = 8,
		maxr = Math.sqrt(w*w + h*h),
		density = .4

	var cloud = document.createElement('div')
	cloud.className = 'cloud'
	for (var x=-w; x<=w; x++) {
		for (var y=-h; y<=h; y++) {
			r = Math.sqrt(x*x + y*y)
			if (r/maxr < Math.pow(Math.random(), density)) {
				var puff = document.createElement('div')
				puff.className = 'puff'
				puff.style.left = (x + w) * PIXEL_SIZE + 'px'
				puff.style.top = (y + h) * PIXEL_SIZE + 'px'
				cloud.appendChild(puff)
			}
		}
	}
	return cloud
}

clouds = []

function randomPosition(max) {
	return Math.round(randomInt(-400, max)/PIXEL_SIZE)*PIXEL_SIZE
}

function addCloud(randomLeft) {
	var cloudiness = .3

	if (Math.random() < cloudiness) {
		newCloud = {
			x: randomLeft ? randomPosition(document.documentElement.clientWidth) : -400,
			el: makeCloud()
		}

		newCloud.el.style.top = randomPosition(document.documentElement.clientHeight) + 'px'
		newCloud.el.style.left = newCloud.x + 'px'
		document.body.appendChild(newCloud.el)
		clouds.push(newCloud)
	}
}

function animateClouds() {
	var speed = 25

	addCloud()

	var newClouds = []
	for (var i=0; i<clouds.length; i++) {
		var cloud = clouds[i]
		cloud.x += speed

		if (cloud.x > document.documentElement.clientWidth) {
			document.body.removeChild(cloud.el)
		} else {
			cloud.el.style.left = cloud.x + 'px'
			newClouds.push(cloud)
		}
	}

	clouds = newClouds
}

function start() {
	if (arguments.callee.ran) { return; }
	arguments.callee.ran = true

	setInterval(animateClouds, 2*1000)

	for (n=0; n<50; n++) {
		addCloud(true)
	}
}

if (document.addEventListener) {
	document.addEventListener('DOMContentLoaded', start, false)
}
window.onload = start
</script>
</head>
{/literal}
<body>
	<div id="container">
		<div id="title">
			
			<h1 role="heading">The {$libraryName|regex_replace:"/catalog\s*$/i":""} Catalog is Down</h1>{* strip from any library name that ends with the word catalog to avoid duplication *}
			<h2>for maintenance</h2>
			{if $maintenanceMessage}
				<div class="h3">{$maintenanceMessage}</div>
			{/if}
			{if $showLinkToClassicInMaintenanceMode}
			<div class="h4"> Please search our <a href="{$classicCatalogUrl}">Classic Catalog</a></div>
			{/if}
		</div>
		<!-- server ip {$activeIp} -->
	</div>
</body>
</html>