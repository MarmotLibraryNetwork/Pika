{* This template doesn't use taxonomy_wrapper.tpl *}
{strip}
	<div class="row">
		<div class="col-sm-12">
			{include file="Archive/search-results-navigation.tpl"}
			<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>
		</div>
	</div>
	<div class="row">
		<div class="col-xl-6">
			{if $thumbnail && $thumbnail.url}
				<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-responsive taxonomy-thumbnail">
			{/if}
	</div>
	<div class="col-xl-6">
		{include file="Archive2/partials/fieldRow.tpl" label="Given Name"     value=$given_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Middle Name"    value=$middle_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Family Name"    value=$family_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Maiden Name"    value=$maiden_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Birth Year"     value=$birth_year isDate=true}
		{include file="Archive2/partials/fieldRow.tpl" label="Death Year"     value=$death_year isDate=true}
		{include file="Archive2/partials/fieldRow.tpl" label="Race/Ethnicity" value=$race_ethnicity}
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

<div class=" taxonomy-detail taxonomy-person">
	<div id="more-details-accordion" class="panel-group">

		{if $wikipediaData}
				<div class="panel active" id="personWikipediaPanel">
					<a data-bs-toggle="collapse" href="#personWikipediaPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">From Wikipedia</h2>
						</div>
					</a>
					<div id="personWikipediaPanelBody" class="panel-collapse collapse in">
						<div class="panel-body">
							{include file="Archive2/sections/wikipediaSection.tpl"}
						</div>
					</div>
				</div>
			{/if}

			{* Related Objects — populated via AJAX on page load *}
			<div class="panel active" id="personRelatedObjectsPanel">
				<a data-bs-toggle="collapse" href="#personRelatedObjectsPanelBody">
					<div class="panel-heading">
						<h2 class="panel-title">Related Objects</h2>
					</div>
				</a>
				<div id="personRelatedObjectsPanelBody" class="panel-collapse collapse in">
					<div class="panel-body" id="personRelatedObjectsContent">
						Loading...
					</div>
				</div>
			</div>

			{if $notes}
				<div class="panel" id="personNotesPanel">
					<a data-bs-toggle="collapse" href="#personNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="personNotesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row">
								<div class="col-md-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
			{if $obituaries}
				<div class="panel active" id="personObituariesPanel">
					<a data-bs-toggle="collapse" href="#personObituariesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Obituaries</h2>
						</div>
					</a>
					<div id="personObituariesPanelBody" class="panel-collapse collapse in">
						<div class="panel-body">
							{foreach from=$obituaries item=obituary}
								<p class="obituaryTitle">
									{$obituary->source}{if $obituary->sourcePage} page {$obituary->sourcePage}{/if}{if $obituary->formattedObitDate()} - {$obituary->formattedObitDate()}{/if}
								</p>
								{if $obituary->picture}
									<p class="obituaryPicture">
										<a href="{$obituary->getImageUrl('large')}"><img class="obitPicture" src="{$obituary->getImageUrl('medium')}" alt="Image of Obituary Text"></a>
									</p>
									<div class="clearer"></div>
								{/if}
								{if $obituary->contents}
									<p class="obituaryText">{$obituary->contents|escape|replace:"\r":"<br>"}</p>
									<div class="clearer"></div>
								{/if}
							{/foreach}
						</div>
					</div>
				</div>
			{/if}
			{if $burial}
				<div class="panel" id="personBurialPanel">
					<a data-bs-toggle="collapse" href="#personBurialPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Burial Details</h2>
						</div>
					</a>
					<div id="personBurialPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Cemetery Name"     value=$burial.cemetery_name}
							{include file="Archive2/partials/fieldRow.tpl" label="Cemetery Location" value=$burial.cemetery_location}
							{include file="Archive2/partials/fieldRow.tpl" label="Cemetery Avenue"   value=$burial.cemetery_avenue}
							{if $burial.addition || $burial.block || $burial.lot || $burial.grave}
								<div class="row archive-field-row">
									<div class="result-label col-sm-4">Burial Location: </div>
									<div class="result-value col-sm-8">
										{if $burial.addition}Addition {$burial.addition|escape}{if $burial.block || $burial.lot || $burial.grave}, {/if}{/if}
										{if $burial.block}Block {$burial.block|escape}{if $burial.lot || $burial.grave}, {/if}{/if}
										{if $burial.lot}Lot {$burial.lot|escape}{if $burial.grave}, {/if}{/if}
										{if $burial.grave}Grave {$burial.grave|escape}{/if}
									</div>
								</div>
							{/if}
							{include file="Archive2/partials/fieldRow.tpl" label="Tombstone Inscription" value=$burial.tombstone_inscription}
							{include file="Archive2/partials/fieldRow.tpl" label="Mortuary Name"         value=$burial.mortuary_name}
						</div>
					</div>
				</div>
			{/if}

			{if $military}
				<div class="panel" id="personMilitaryPanel">
					<a data-bs-toggle="collapse" href="#personMilitaryPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Military Service</h2>
						</div>
					</a>
					<div id="personMilitaryPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{if $military.branch}
								<div class="row archive-field-row">
									<div class="result-label col-sm-4">Service Branch: </div>
									<div class="result-value col-sm-8">
										{if $military.branch_url}
											<a href="{$military.branch_url|escape}">{$military.branch|escape}</a>
										{else}
											{$military.branch|escape}
										{/if}
									</div>
								</div>
							{/if}
							{if $military.conflict}
								<div class="row archive-field-row">
									<div class="result-label col-sm-4">Conflict: </div>
									<div class="result-value col-sm-8">
										{if $military.conflict_url}
											<a href="{$military.conflict_url|escape}">{$military.conflict|escape}</a>
										{else}
											{$military.conflict|escape}
										{/if}
									</div>
								</div>
							{/if}
							{include file="Archive2/partials/fieldRow.tpl" label="Highest Rank Attained" value=$military.rank}
							{include file="Archive2/partials/fieldRow.tpl" label="Service Start"   value=$military.svc_start isDate=true}
							{include file="Archive2/partials/fieldRow.tpl" label="Service End"     value=$military.svc_end   isDate=true}
							{if $military.is_pow}
								<div class="row archive-field-row">
									<div class="result-label col-md-4">Prisoner Of War:</div>
									<div class="result-value col-md-8">Yes</div>
								</div>
							{/if}
						</div>
					</div>
				</div>
			{/if}

			{if $academic}
				<div class="panel" id="personAcademicPanel">
					<a data-bs-toggle="collapse" href="#personAcademicPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Academic Information</h2>
						</div>
					</a>
					<div id="personAcademicPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Position Title"      value=$academic.position_title}
							{include file="Archive2/partials/fieldRow.tpl" label="Position Start Date" value=$academic.position_start isDate=true}
							{include file="Archive2/partials/fieldRow.tpl" label="Position End Date"   value=$academic.position_end   isDate=true}
							{include file="Archive2/partials/fieldRow.tpl" label="Degree"              value=$academic.degree_name}
							{include file="Archive2/partials/fieldRow.tpl" label="Degree Discipline"   value=$academic.discipline}
							{include file="Archive2/partials/fieldRow.tpl" label="Graduation Date"     value=$academic.graduation_date isDate=true}
						</div>
					</div>
				</div>
			{/if}

			{include file="Archive2/taxonomy_external_links.tpl"}

			{include file="Archive2/panels/sharedRelatedPanels.tpl"}

		</div>
	</div>
	{include file="Archive2/panels/taxonomy_metadata_panel.tpl"}

	<script>
		Pika.Archive2.loadRelatedObjects('{$term_title|escape:"javascript"}');
		Pika.Archive2.loadTaxonomyExploreMore({$tid});
	</script>
{/strip}