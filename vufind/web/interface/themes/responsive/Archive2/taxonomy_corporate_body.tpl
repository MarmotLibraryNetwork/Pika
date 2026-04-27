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
					style="max-width:280px; margin:0; float: left;">
			{/if}
		</div>
		<div class="col-xs-8">
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
	<div class="taxonomy-detail taxonomy-corporate-body">
		<div id="corporate-body-detail-accordion" class="panel-group">

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

			{if $related_place}
				<div class="panel" id="corpBodyRelatedPlacePanel">
					<a data-toggle="collapse" href="#corpBodyRelatedPlacePanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Address</h2>
						</div>
					</a>
					<div id="corpBodyRelatedPlacePanelBody" class="panel-collapse collapse">
						<div class="panel-body">

							{if is_array($related_place) && $related_place.tid}
								<div class="row archive-field-row">
									<div class="result-label col-sm-4">
										{$related_place.relation_label|escape} {if $related_place.start_date_rel_place}{$related_place.start_date_rel_place} -{/if} {if $related_place.end_date_rel_place}{$related_place.end_date_rel_place}{/if}
									</div>
									<div class="result-value col-sm-8">
										<a href="/Archive2/Place?tid={$related_place.tid}">{$related_place.name|escape}</a>
									</div>
								</div>
							{else}
								{foreach from=$related_place item=place}
									<div class="row archive-field-row">
										<div class="result-label col-sm-4">
											{$place.relation_label|escape} {if $place.start_date_rel_place|escape}{$place.start_date_rel_place} -{/if} {if $place.end_date_rel_place}{$place.end_date_rel_place}{/if}
										</div>
										<div class="result-value col-sm-8">
											<a href="/Archive2/Place/{$place.tid}">{$place.name|escape}</a>
										</div>
									</div>
								{/foreach}
							{/if}
						</div>
					</div>
				</div>
			{/if}

			{include file="Archive2/panels/sharedRelatedPanels.tpl"}
			{include file="Archive2/panels/subjectsPanel.tpl"}
		</div>
	</div>

	{include file="Archive2/panels/taxonomy_metadata_panel.tpl"}

	<script>
		Pika.Archive2.loadRelatedObjectsForOrganization('{$term_title|escape:"javascript"}');
	</script>
{/strip}