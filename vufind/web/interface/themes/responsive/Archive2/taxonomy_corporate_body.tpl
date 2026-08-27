{strip}
	<div class="row">
		<div class="col-xs-12">
			{include file="Archive2/search-results-navigation.tpl"}
			<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6">
			{if $thumbnail && $thumbnail.url}
				<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-responsive taxonomy-thumbnail">
			{/if}
		</div>
		<div class="col-lg-6">
			{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name"     value=$alternate_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Organization Type"  value=$organization_type}
			{if $organization_url && $organization_url.uri}
				<div class="row archive-field-row">
					<div class="result-label col-sm-4">Website:</div>
					<div class="result-value col-sm-8">
						<a href="{$organization_url.uri|escape}" target="_blank" rel="noopener">
							{if $organization_url.title}{$organization_url.title|escape}{else}{$organization_url.uri|escape}{/if}
						</a>
					</div>
				</div>
			{/if}
			{include file="Archive2/partials/fieldRow.tpl" label="Founded"            value=$founded_year}
			{include file="Archive2/partials/fieldRow.tpl" label="Dissolved"          value=$dissolved_year}
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

	{include file="Archive2/taxonomy_tools.tpl"}

	<div class="taxonomy-detail taxonomy-corporate-body">
		<div id="more-details-accordion" class="panel-group">

			{if $wikipediaData}
				<div class="panel active" id="orgWikipediaPanel">
					<a data-toggle="collapse" href="#orgWikipediaPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">From Wikipedia</h2>
						</div>
					</a>
					<div id="orgWikipediaPanelBody" class="panel-collapse collapse in">
						<div class="panel-body">
							{include file="Archive2/sections/wikipediaSection.tpl"}
						</div>
					</div>
				</div>
			{/if}

			{* Related Objects — populated via AJAX on page load *}
			<div class="panel active" id="orgRelatedObjectsPanel">
				<a data-toggle="collapse" href="#orgRelatedObjectsPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Related Objects</h2>
					</div>
				</a>
				<div id="orgRelatedObjectsPanelBody" class="panel-collapse collapse in">
					<div class="panel-body" id="orgRelatedObjectsContent">
						Loading...
					</div>
				</div>
			</div>

			{include file="Archive2/panels/mapPanel.tpl"}

			{if $org_addresses}
				<div class="panel" id="corpBodyAddressesPanel">
					<a data-toggle="collapse" href="#corpBodyAddressesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Addresses</h2>
						</div>
					</a>
					<div id="corpBodyAddressesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/sections/addressesSection.tpl" interview_locations=$org_addresses}
						</div>
					</div>
				</div>
			{/if}

			{if $notes}
				<div class="panel" id="corpBodyNotesPanel">
					<a data-toggle="collapse" href="#corpBodyNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="corpBodyNotesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-12">{$notes}</div>
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
		Pika.Archive2.loadRelatedObjectsForOrganization('{$term_title|escape:"javascript"}');
		Pika.Archive2.loadTaxonomyExploreMore({$tid});
	</script>
{/strip}