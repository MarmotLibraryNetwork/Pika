{strip}
	{include file="Archive/search-results-navigation.tpl"}
	<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>

	<div class="col-xs-6">
		{if $thumbnail && $thumbnail.url}
			<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-responsive taxonomy-thumbnail "
				style="max-width:390px; margin:0; float: left;">
		{/if}
	</div>
	<div class="col-xs-6">

		{if $term_description}
			<div class="taxonomy-description well">
				{$term_description}
			</div>
		{/if}
		{if $address}
			<div class="taxonomy-address">
				<div class="result-label">Address</div>
				{if $address.street}
					{$address.street}<br />
				{/if}
				{if $address.city}
					{$address.city}<br />
				{/if}
				{if $address.state}
					{$address.state}<br />
				{/if}
			</div>
		{/if}
	</div>
	<br class="clearfix" style="clear: both">
	<div class="taxonomy-detail taxonomy-geographic-location">
		<div id="geo-location-detail-accordion" class="panel-group">

			<div class="panel" id="geoLocationPanel">
				<a data-toggle="collapse" href="#geoLocationPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Location</h2>
					</div>
				</a>
				<div id="geoLocationPanelBody" class="panel-collapse collapse in">
					<div class="panel-body">
						{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name"    value=$alternate_name}
						{include file="Archive2/partials/fieldRow.tpl" label="Broader Location"  value=$broader_location}
						{include file="Archive2/partials/fieldRow.tpl" label="Start Date"        value=$start_date}
						{include file="Archive2/partials/fieldRow.tpl" label="End Date"          value=$end_date}
					</div>
				</div>
			</div>

			{if $notes}
				<div class="panel" id="geoNotesPanel">
					<a data-toggle="collapse" href="#geoNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="geoNotesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}

			{if $geolocation}
				<div class="panel" id="geoCoordinatesPanel">
					<a data-toggle="collapse" href="#geoCoordinatesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Coordinates</h2>
						</div>
					</a>
					<div id="geoCoordinatesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{if is_array($geolocation)}
								{include file="Archive2/partials/fieldRow.tpl" label="Latitude"  value=$geolocation.lat}
								{include file="Archive2/partials/fieldRow.tpl" label="Longitude" value=$geolocation.lng}
								<div class="row">
									<div class="col-sm-12">
										<div id="taxonomy-map" data-lat="{$geolocation.lat}" data-lon="{$geolocation.lon}"
											class="taxonomy-map-placeholder"
											style="height:300px; background:#eee; display:flex; align-items:center; justify-content:center; margin-top:10px;">
											<span class="text-muted">[Map — {$geolocation.lat}, {$geolocation.lng}]</span>
										</div>
									</div>
								</div>
							{else}
								{include file="Archive2/partials/fieldRow.tpl" label="Coordinates" value=$geolocation}
							{/if}
						</div>
					</div>
				</div>
			{/if}

			{if $related_place}
				<div class="panel" id="geoRelatedPlacePanel">
					<a data-toggle="collapse" href="#geoRelatedPlacePanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Related Place</h2>
						</div>
					</a>
					<div id="geoRelatedPlacePanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Place" value=$related_place}
						</div>
					</div>
				</div>
			{/if}

		</div>
	</div>
{/strip}