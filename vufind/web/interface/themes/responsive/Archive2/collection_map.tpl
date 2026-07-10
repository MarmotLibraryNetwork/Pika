{strip}
	<div class="col-sm-12">
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}
			{include file="Archive/noAccess.tpl"}
			
		{else}

			{if $thumbnail}
				<div class="row">
					<div class="col-sm-12">
						<img src="{$thumbnail}" class="img-fluid thumbnail collection-thumbnail-float-left" alt="{$title|escape}">
						{$description}
						<div class="clearfix"></div>
					</div>
				</div>
			{elseif $description}
				<div class="row">
					<div class="col-sm-12">{$description}</div>
				</div>
			{/if}

			<div class="row collection-row-spacer">
				<div class="col-sm-12">
					<div id="collection-map" class="col-sm-12 collection-map-container"></div>
				</div>
			</div>

			{if $unmappedPlaces}
				<div class="row collection-row-spacer-sm">
					<div class="col-sm-12">
						<button class="btn btn-info btn-xs"
							onclick="Pika.showElementInPopup('Unmapped Locations', '#unmappedLocations');">
							Show Unmapped Locations
						</button>
						<div id="unmappedLocations" class="archive-map-unmapped-locations">
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

	{include file="Archive2/components/search_component.tpl"}

	{include file="Archive2/components/timeline_component.tpl"}

	{*include file="Archive2/metadata.tpl"*}
{/strip}

{if $mapsKey && ($mappedPlaces || $childMarkers)}
	<script>
		function initCollectionMap() {ldelim}
		var mapEl = document.getElementById('collection-map');
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
				Pika.Archive2.setTimelinePlace('{$place.label|escape:javascript}');
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
			var html = '<div class="archive-map-popup">';
			{if $child.thumbnail}html += '<a href="{$child.url|escape:javascript}"><img src="{$child.thumbnail|escape:javascript}" class="archive-map-popup-thumbnail" /></a><br>';{/if}
			html += '<a href="{$child.url|escape:javascript}">{$child.title|escape:javascript}</a></div>';
			infoWindow.setContent(html);
			infoWindow.open({ldelim}anchor: marker, map: map{rdelim});
			{rdelim});
			{rdelim})();
		{/foreach}
		{rdelim}
	</script>
	<script
		src="https://maps.googleapis.com/maps/api/js?key={$mapsKey}&loading=async&libraries=marker&callback=initCollectionMap" async defer></script>
{/if}

{literal}
	<script>
		$().ready(function() {
			Pika.Archive2.loadExploreMore({/literal}{$nid}{literal});
		});
	</script>
{/literal}
