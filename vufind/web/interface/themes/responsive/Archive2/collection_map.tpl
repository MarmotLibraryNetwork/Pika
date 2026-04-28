{strip}
<div class="col-xs-12">
	{include file="Archive/search-results-navigation.tpl"}
	<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

	{if $can_view == false}
		{include file="Archive/noAccess.tpl"}
	{else}

	{if $thumbnail}
	<div class="row">
		<div class="col-xs-12">
			<img src="{$thumbnail}" class="img-responsive thumbnail" style="max-width:300px; float:left; margin: 0 1em 1em 0;" alt="{$title|escape}">
			{$description}
			<div class="clearfix"></div>
		</div>
	</div>
	{elseif $description}
	<div class="row">
		<div class="col-xs-12">{$description}</div>
	</div>
	{/if}

	<div class="row" style="margin-top:1em;">
		<div id="collection-map" class="col-xs-12" style="height:450px;"></div>
	</div>

	{if $unmappedPlaces}
	<div class="row" style="margin-top:0.5em;">
		<div class="col-xs-12">
			<button class="btn btn-info btn-xs" onclick="Pika.showElementInPopup('Unmapped Locations', '#unmappedLocations');">
				Show Unmapped Locations
			</button>
			<div id="unmappedLocations" style="display:none;">
				<ol>
					{foreach from=$unmappedPlaces item=place}
					<li><a href="{$place.url}">{$place.label}</a></li>
					{/foreach}
				</ol>
			</div>
		</div>
	</div>
	{/if}

	{/if}
</div>
{/strip}

{if $mapsKey && $mappedPlaces}
<script>
function initCollectionMap() {ldelim}
	var mapEl = document.getElementById('collection-map');
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

	{foreach from=$mappedPlaces item=place name=pl}
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
<script src="https://maps.googleapis.com/maps/api/js?key={$mapsKey}&callback=initCollectionMap" async defer></script>
{/if}

{literal}
<script>
$().ready(function(){
	Pika.Archive2.loadExploreMore({/literal}{$nid}{literal});
});
</script>
{/literal}
