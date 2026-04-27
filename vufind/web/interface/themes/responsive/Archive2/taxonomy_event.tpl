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
			{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Stard Date"     value=$start_date}
			{include file="Archive2/partials/fieldRow.tpl" label="End Date"       value=$end_date}
			{include file="Archive2/partials/fieldRow.tpl" label="City"           value=$event_city}
			{include file="Archive2/partials/fieldRow.tpl" label="County"         value=$event_county}
			{include file="Archive2/partials/fieldRow.tpl" label="State"          value=$event_state}
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

	<div class="taxonomy-detail taxonomy-event">
		<div id="event-detail-accordion" class="panel-group">

			{* Related Objects — populated via AJAX on page load *}
			<div class="panel active" id="eventRelatedObjectsPanel">
				<a data-toggle="collapse" href="#eventRelatedObjectsPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Related Objects</h2>
					</div>
				</a>
				<div id="eventRelatedObjectsPanelBody" class="panel-collapse collapse in">
					<div class="panel-body" id="eventRelatedObjectsContent">
						Loading...
					</div>
				</div>
			</div>

			{if $notes}
				<div class="panel" id="eventNotesPanel">
					<a data-toggle="collapse" href="#eventNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="eventNotesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
			{include file="Archive2/panels/sharedRelatedPanels.tpl"}
			{include file="Archive2/panels/subjectsPanel.tpl"}
			
		</div>
	</div>
	{*include file="Archive2/taxonomy_related_objects.tpl"*}
	{include file="Archive2/panels/taxonomy_metadata_panel.tpl"}

	<script>
		Pika.Archive2.loadRelatedObjectsForEvent('{$term_title|escape:"javascript"}');
	</script>
{/strip}