{strip}
<div class="nopadding col-sm-12">
	<div class="archiveComponent">
		<div class="archiveComponentHeader">Browse by Location</div>
		<div id="collection-map-component" style="height:400px; margin-top:0.5em;"></div>
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

{if $mapsKey && $mappedPlaces}
<script>
function initCollectionMapComponent() {ldelim}
	var mapEl = document.getElementById('collection-map-component');
	if (!mapEl) return;

	var map = new google.maps.Map(mapEl, {ldelim}
		center: {ldelim}lat: {$mapCenterLat|default:0}, lng: {$mapCenterLong|default:0}{rdelim},
		zoom: {$mapZoom|default:9}
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
		var marker = new google.maps.Marker({ldelim}
			position: {ldelim}lat: {$place.latitude}, lng: {$place.longitude}{rdelim},
			map: map,
			title: '{$place.label|escape:javascript}'
		{rdelim});
		marker.addListener('click', function() {ldelim}
			infoWindow.setContent('<a href="{$place.url|escape:javascript}">{$place.label|escape:javascript}</a>');
			infoWindow.open(map, marker);
		{rdelim});
	{rdelim})();
	{/if}
	{/foreach}
{rdelim}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={$mapsKey}&callback=initCollectionMapComponent" async defer></script>
{/if}
