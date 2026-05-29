{strip}
<div class="col-sm-12">
	<div class="archiveComponent">
		<div class="archiveComponentHeader">Browse by Location</div>
		<div id="collection-map-component" style="height:500px; margin-top:0.5em;"></div>
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

{if $mapsKey && ($mappedPlaces || $childMarkers)}
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
<script>
function initCollectionMapComponent() {ldelim}
	var mapEl = document.getElementById('collection-map-component');
	if (!mapEl) return;

	var map = new google.maps.Map(mapEl, {ldelim}
		center: {ldelim}lat: {$mapCenterLat|default:0}, lng: {$mapCenterLong|default:0}{rdelim},
		zoom: {$mapZoom|default:9},
		mapId: "{$mapsId|default:'DEMO_MAP_ID'}"
	{rdelim});

	{if $minLat && $maxLat && $minLong && $maxLong}
	map.fitBounds({ldelim}
		south: {$minLat}, west: {$minLong},
		north: {$maxLat}, east: {$maxLong}
	{rdelim});
	{/if}

	var infoWindow = new google.maps.InfoWindow();

	{foreach from=$mappedPlaces item=place}
	{if $place.latitude && $place.longitude}
	(function() {ldelim}
		var count = {$place.count|default:0};
		var pin = document.createElement('div');
		pin.className = 'map-count-marker';
		pin.textContent = count;
		var marker = new google.maps.marker.AdvancedMarkerElement({ldelim}
			position: {ldelim}lat: {$place.latitude}, lng: {$place.longitude}{rdelim},
			map: map,
			title: '{$place.label|escape:javascript}',
			content: pin
		{rdelim});
		marker.addListener('gmp-click', function() {ldelim}
			infoWindow.close();
			infoWindow.setContent('<a href="{$place.url|escape:javascript}">{$place.label|escape:javascript}</a><br>' + count + ' items for this location');
			infoWindow.open({ldelim}anchor: marker, map: map{rdelim});
		{rdelim});
	{rdelim})();
	{/if}
	{/foreach}

	{foreach from=$childMarkers item=child}
	(function() {ldelim}
		var marker = new google.maps.marker.AdvancedMarkerElement({ldelim}
			position: {ldelim}lat: {$child.latitude}, lng: {$child.longitude}{rdelim},
			map: map,
			title: '{$child.title|escape:javascript}'
		{rdelim});
		marker.addListener('gmp-click', function() {ldelim}
			infoWindow.close();
			var html = '<div style="max-width:220px;text-align:center">';
			{if $child.thumbnail}html += '<a href="{$child.url|escape:javascript}"><img src="{$child.thumbnail|escape:javascript}" style="max-width:200px;margin-bottom:6px" /></a><br>';{/if}
			html += '<a href="{$child.url|escape:javascript}">{$child.title|escape:javascript}</a></div>';
			infoWindow.setContent(html);
			infoWindow.open({ldelim}anchor: marker, map: map{rdelim});
		{rdelim});
	{rdelim})();
	{/foreach}
{rdelim}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={$mapsKey}&loading=async&libraries=marker&callback=initCollectionMapComponent" async defer></script>
{/if}
