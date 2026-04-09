{* This template doesn't use taxonomy_wrapper.tpl *}
{strip}
	<div class="row">
		<div class="col-xs-12">
			{include file="Archive/search-results-navigation.tpl"}
			<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-4">
			{if $thumbnail && $thumbnail.url}
				<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-responsive taxonomy-thumbnail"
					style="max-width:400px; margin:0; float: left;">
			{/if}
		</div>
		<div class="col-xs-8">
			{if $alternate_name}
				{* TODO: this should handle an array names *}
				{if is_array($alternate_name)}
					<div class="row taxonomy-alt_name">
						<div class="col-xs-4 result-label">Other Names</div>
						<div class="col-xs-8">{$alternate_name}</div>
					</div>
				{else}
					<div class="row taxonomy-alt_name">
						<div class="col-xs-4 result-label">Other Name</div>
						<div class="col-xs-8">{$alternate_name}</div>
					</div>
				{/if}
			{/if}
			{if $start_date || $end_date}
				{if $start_date}
					<div class="row taxonomy-start_date">
						<div class="col-xs-4 result-label">Founded</div>
						<div class="col-xs-8">{$start_date}</div>
					</div>
				{/if}
				{if $end_date}
					<div class="row taxonomy-end_date">
						<div class="col-xs-4 result-label">Dissolved</div>
						<div class="col-xs-8">{$end_date}</div>
					</div>
				{/if}
			{/if}
			{if $address}
				<div class="row taxonomy-address">
					<div class="col-xs-4 result-label">Address</div>
					<div class="col-xs-8">
						{if $address.street}
							{$address.street}<br />
						{/if}
						{if $address.city}{$address.city}{if $address.state},&nbsp;{/if}{/if}{if $address.state}{$address.state}&nbsp;{/if}
						{if $address.zip_code}{$address.zip_code}{/if}
					</div>
				</div>
			{/if}
			{if $address.county}
				<div class="row taxonomy-address-state">
					<div class="col-xs-4 result-label">County</div>
					<div class="col-xs-8">{$address.county}</div>
				</div>
			{/if}
			{if $address.country}
				<div class="row taxonomy-address-state">
					<div class="col-xs-4 result-label">Country</div>
					<div class="col-xs-8">{$address.country}</div>
				</div>
			{/if}
		</div>
	</div>
	<br class="clearfix">
	{if $term_description}
		<div class="row">
			<div class="col-xs-12">
				<div class="taxonomy-description">
					{$term_description}
				</div>

			</div>
		</div>
	{/if}

	<div class="taxonomy-detail taxonomy-geographic-location">
		<div id="geo-location-detail-accordion" class="panel-group">

			{if $geolocation}
				<div class="panel active" id="geoCoordinatesPanel">
					<a data-toggle="collapse" href="#geoCoordinatesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Map</h2>
						</div>
					</a>
					<div id="geoCoordinatesPanelBody" class="panel-collapse collapse in">
						<div class="panel-body">
							{if is_array($geolocation)}
								{include file="Archive2/partials/fieldRow.tpl" label="Latitude"  value=$geolocation.lat}
								{include file="Archive2/partials/fieldRow.tpl" label="Longitude" value=$geolocation.lng}
								<div class="row">
									<div class="col-sm-12">
										
										<iframe title="Google map for {$title}" width="100%" height="300px" style="border:0" 
											src="https://www.google.com/maps/embed/v1/place?q={$geolocation.lat|escape}%2C%20{$geolocation.lng|escape}&key={$mapsKey}" allowfullscreen></iframe>
									</div>
								</div>
							{else}
								{include file="Archive2/partials/fieldRow.tpl" label="Coordinates" value=$geolocation}
							{/if}
						</div>
					</div>
				</div>
			{/if}
			{if $notes}
				<div class="panel active" id="geoNotesPanel">
					<a data-toggle="collapse" href="#geoNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="geoNotesPanelBody" class="panel-collapse collapse in">
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
			
			{include file="Archive2/panels/taxonomy_related_panels.tpl"}

		</div>
	</div>
	{include file="Archive2/taxonomy_related_objects.tpl"}

	{include file="Archive2/taxonomy_metadata.tpl"}
{/strip}