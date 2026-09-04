{strip}
	<div class="row">
		<div class="col-sm-12">
			{include file="Archive2/search-results-navigation.tpl"}
			<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>
		</div>
	</div>
	<div class="row">
		<div class="col-xl-6">
			{if $thumbnail && $thumbnail.url}
				<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-fluid taxonomy-thumbnail">
			{/if}
		</div>
		<div class="col-xl-6">
			{if $alternate_name}
				<div class="row taxonomy-alt_name">
					<div class="col-sm-4 result-label">Other Names</div>
					<div class="col-sm-8">{foreach from=$alternate_name item=name}{$name}<br>{/foreach}</div>
				</div>
			{/if}
			{if $start_date || $end_date}
				{if $start_date}
					<div class="row taxonomy-start_date">
						<div class="col-sm-4 result-label">Founded</div>
						<div class="col-sm-8">{$start_date}</div>
					</div>
				{/if}
				{if $end_date}
					<div class="row taxonomy-end_date">
						<div class="col-sm-4 result-label">Dissolved</div>
						<div class="col-sm-8">{$end_date}</div>
					</div>
				{/if}
			{/if}
			{if $address}
				{*TODO: only show Address in Address section? *}
				<div class="row taxonomy-address">
					<div class="col-sm-4 result-label">Address</div>
					<div class="col-sm-8">
						{if $address.street}
							{$address.street}<br>
						{/if}
						{if $address.city}{$address.city}{if $address.state},&nbsp;{/if}{/if}{if $address.state}{$address.state}&nbsp;{/if}
						{if $address.zip_code}{$address.zip_code}{/if}
					</div>
				</div>
			{/if}
			{if $address.county}
				<div class="row taxonomy-address-state">
					<div class="col-sm-4 result-label">County</div>
					<div class="col-sm-8">{$address.county}</div>
				</div>
			{/if}
			{if $address.country}
				<div class="row taxonomy-address-state">
					<div class="col-sm-4 result-label">Country</div>
					<div class="col-sm-8">{$address.country}</div>
				</div>
			{/if}
		</div>
	</div>
	<br class="clearfix">
	{if $term_description}
		<div class="row">
			<div class="col-sm-12">
				<div class="taxonomy-description">
					{$term_description}
				</div>
			</div>
		</div>
	{/if}

	{include file="Archive2/taxonomy_tools.tpl"}

	<div class="taxonomy-detail taxonomy-geographic-location">
		<div id="more-details-accordion" class="panel-group">

			{if $wikipediaData}
				<div class="panel active" id="placeWikipediaPanel">
					<a data-bs-toggle="collapse" href="#placeWikipediaPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">From Wikipedia</h2>
						</div>
					</a>
					<div id="placeWikipediaPanelBody" class="panel-collapse collapse show">
						<div class="panel-body">
							{include file="Archive2/sections/wikipediaSection.tpl"}
						</div>
					</div>
				</div>
			{/if}

			{* Related Objects — populated via AJAX on page load *}
			<div class="panel active" id="placeRelatedObjectsPanel">
				<a data-bs-toggle="collapse" href="#placeRelatedObjectsPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Related Objects</h2>
					</div>
				</a>
				<div id="placeRelatedObjectsPanelBody" class="panel-collapse collapse show">
					<div class="panel-body" id="placeRelatedObjectsContent">
						Loading...
					</div>
				</div>
			</div>

			{include file="Archive2/panels/mapPanel.tpl"}
			{if $place_addresses}
				<div class="panel" id="geoAddressesPanel">
					<a data-bs-toggle="collapse" href="#geoAddressesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Addresses</h2>
						</div>
					</a>
					<div id="geoAddressesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/sections/addressesSection.tpl" interview_locations=$place_addresses}
						</div>
					</div>
				</div>
			{/if}

			{if $notes}
				<div class="panel active" id="geoNotesPanel">
					<a data-bs-toggle="collapse" href="#geoNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="geoNotesPanelBody" class="panel-collapse collapse show">
						<div class="panel-body">
							<div class="row">
								<div class="col-md-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
			{include file="Archive2/taxonomy_external_links.tpl"}
			{include file="Archive2/panels/sharedRelatedPanels.tpl"}
			{include file="Archive2/panels/subjectsPanel.tpl"}
		</div>
	</div>

	{include file="Archive2/panels/taxonomy_metadata_panel.tpl"}

	<script>
		Pika.Archive2.loadRelatedObjectsForPlace('{$term_title|escape:"javascript"}');
		Pika.Archive2.loadTaxonomyExploreMore({$tid});
	</script>
{/strip}