{if $geolocation}
	<div class="panel active" id="taxonomyMapPanel">
		<a data-toggle="collapse" href="#taxonomyMapPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Map</h2>
			</div>
		</a>
		<div id="taxonomyMapPanelBody" class="panel-collapse collapse in">
			<div class="panel-body">
				{if is_array($geolocation)}
					{include file="Archive2/partials/fieldRow.tpl" label="Latitude"  value=$geolocation.lat}
					{include file="Archive2/partials/fieldRow.tpl" label="Longitude" value=$geolocation.lng}
					<div class="row">
						<div class="col-sm-12">
							<iframe title="Google map for {$term_title|escape}" width="100%" height="300px" class="taxonomy-map-embed"
								src="https://www.google.com/maps/embed/v1/place?q={$geolocation.lat|escape}%2C%20{$geolocation.lng|escape}&key={$maps_key|escape}" allowfullscreen></iframe>
						</div>
					</div>
				{else}
					{include file="Archive2/partials/fieldRow.tpl" label="Coordinates" value=$geolocation}
				{/if}
			</div>
		</div>
	</div>
{/if}