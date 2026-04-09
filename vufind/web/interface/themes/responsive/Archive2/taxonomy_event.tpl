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
			{include file="Archive2/partials/fieldRow.tpl" label="Stard Date"         value=$start_date}
			{include file="Archive2/partials/fieldRow.tpl" label="End Date"           value=$end_date}
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

			<div class="panel" id="eventDetailsPanel">
				<a data-toggle="collapse" href="#eventDetailsPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Event Details</h2>
					</div>
				</a>
				<div id="eventDetailsPanelBody" class="panel-collapse collapse in">
					<div class="panel-body">
						{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
						{include file="Archive2/partials/fieldRow.tpl" label="Start Year"     value=$start_year}
						{include file="Archive2/partials/fieldRow.tpl" label="End Year"       value=$end_year}
						{include file="Archive2/partials/fieldRow.tpl" label="City"           value=$event_city}
						{include file="Archive2/partials/fieldRow.tpl" label="County"         value=$event_county}
						{include file="Archive2/partials/fieldRow.tpl" label="State"          value=$event_state}
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

			{if $related_place}
				<div class="panel" id="eventRelatedPlacePanel">
					<a data-toggle="collapse" href="#eventRelatedPlacePanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Related Place</h2>
						</div>
					</a>
					<div id="eventRelatedPlacePanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row archive-field-row">
								<div class="result-label col-sm-4">
									{if $related_place.relation_label}{$related_place.relation_label}{else}Place{/if}:
								</div>
								<div class="result-value col-sm-8">
									<a href="{$related_place.url}">{$related_place.name|escape}</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			{/if}

			{if $related_organization}
				<div class="panel" id="eventRelatedOrgPanel">
					<a data-toggle="collapse" href="#eventRelatedOrgPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Related Organization</h2>
						</div>
					</a>
					<div id="eventRelatedOrgPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Organization" value=$related_organization}
						</div>
					</div>
				</div>
			{/if}

		</div>
	</div>
	{include file="Archive2/taxonomy_related_objects.tpl"}

	{include file="Archive2/taxonomy_metadata.tpl"}
{/strip}