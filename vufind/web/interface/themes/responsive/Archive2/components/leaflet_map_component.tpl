{strip}
<div class="col-sm-12">
	<div class="archiveComponent">
		<div id="collection-leaflet-map" style="height:500px; margin-top:0.5em;"></div>
		{if $unmappedPlaces}
		<div style="margin-top:0.5em;">
			<button class="btn btn-info btn-xs" onclick="Pika.showElementInPopup('Unmapped Locations', '#unmappedLocationsComponent');">
				Show Unmapped Locations
			</button>
			<div id="unmappedLocationsComponent" style="display:none;">
				<ol>
					{foreach from=$unmappedPlaces item=place}
					<li><a href="{$place.url}">{$place.label}</a></li>
					{/foreach}
				</ol>
			</div>
		</div>
		{/if}
	</div>
</div>
{/strip}

{if $mappedPlaces}
<style>
.map-count-marker {ldelim}
	background: #2980b9;
	color: #fff;
	border: 2px solid #fff;
	border-radius: 50%;
	width: 32px;
	height: 32px;
	line-height: 28px;
	text-align: center;
	font-size: 11px;
	font-weight: bold;
	box-shadow: 0 1px 4px rgba(0,0,0,0.4);
{rdelim}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {ldelim}
	var center = [{$mapCenterLat|default:0}, {$mapCenterLong|default:0}];
	var zoom   = {$mapZoom|default:9};
	var bounds = {if $minLat && $maxLat && $minLong && $maxLong}[[{$minLat}, {$minLong}], [{$maxLat}, {$maxLong}]]{else}null{/if};
	var places = [
		{foreach from=$mappedPlaces item=place}
		{if $place.latitude && $place.longitude}
		{ldelim}lat: {$place.latitude}, lng: {$place.longitude}, label: '{$place.label|escape:javascript}', url: '{$place.url|escape:javascript}', count: {$place.count|default:0}{rdelim},
		{/if}
		{/foreach}
	];
{literal}
	var map = L.map('collection-leaflet-map').setView(center, zoom);

	var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19
	});

	var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
		attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
		maxZoom: 18
	});

	streetLayer.addTo(map);
	L.control.layers({'Map': streetLayer, 'Satellite': satelliteLayer}).addTo(map);

	places.forEach(function(place) {
		var icon = L.divIcon({
			html: '<div class="map-count-marker">' + place.count + '</div>',
			className: '',
			iconSize: [32, 32],
			iconAnchor: [16, 16],
			popupAnchor: [0, -18]
		});
		var a = document.createElement('a');
		a.href = place.url;
		a.textContent = place.label + ' (' + place.count + ')';
		L.marker([place.lat, place.lng], {icon: icon}).addTo(map).bindPopup(a);
	});

	if (bounds) {
		map.fitBounds(bounds);
	}
{/literal}
{rdelim})();
</script>
{/if}
