{* This template doesn't use taxonomy_wrapper.tpl *}
{strip}
	<div class="row">
		<div class="col-xs-12">
			{include file="Archive/search-results-navigation.tpl"}
			<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-6">
			{if $thumbnail && $thumbnail.url}
				<img src="{$thumbnail.url|escape}" alt="{$term_title|escape}" class="img-responsive taxonomy-thumbnail"
					style="max-width:300px; margin:0;">{* TODO: move to stylesheet *}
			{* removed float; left-floating the image causing the parent div to have no width; and the text would display over image *}
		{/if}
	</div>
	<div class="col-lg-6">
		{include file="Archive2/partials/fieldRow.tpl" label="Given Name"    value=$given_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Middle Name"   value=$middle_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Family Name"   value=$family_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Maiden Name"   value=$maiden_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
		{include file="Archive2/partials/fieldRow.tpl" label="Birth Year"    value=$birth_year isDate=true}
		{include file="Archive2/partials/fieldRow.tpl" label="Death Year"    value=$death_year isDate=true}
		{include file="Archive2/partials/fieldRow.tpl" label="Race/Ethnicity" value=$race_ethnicity}
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

<div class=" taxonomy-detail taxonomy-person">
	<div id="more-details-accordion" class="panel-group">

		{* Related Objects — populated via AJAX on page load *}
			<div class="panel active" id="personRelatedObjectsPanel">
				<a data-toggle="collapse" href="#personRelatedObjectsPanelBody">
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
					<a data-toggle="collapse" href="#personNotesPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Notes</h2>
						</div>
					</a>
					<div id="personNotesPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<div class="row">
								<div class="col-sm-12">{$notes}</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
			{if $obituaries}
				<div class="panel active" id="personObituariesPanel">
					<a data-toggle="collapse" href="#personObituariesPanelBody">
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
					<a data-toggle="collapse" href="#personBurialPanelBody">
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
									<div class="result-label col-xs-4">Burial Location: </div>
									<div class="result-value col-xs-8">
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
					<a data-toggle="collapse" href="#personMilitaryPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Military Service</h2>
						</div>
					</a>
					<div id="personMilitaryPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{if $military.branch}
								<div class="row archive-field-row">
									<div class="result-label col-xs-4">Branch: </div>
									<div class="result-value col-xs-8">
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
									<div class="result-label col-xs-4">Conflict: </div>
									<div class="result-value col-xs-8">
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
									<div class="result-label col-sm-4">POW:</div>
									<div class="result-value col-sm-8">Yes</div>
								</div>
							{/if}
						</div>
					</div>
				</div>
			{/if}

			{if $academic}
				<div class="panel" id="personAcademicPanel">
					<a data-toggle="collapse" href="#personAcademicPanelBody">
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

			{if $links}
				<div class="panel" id="personLinksPanel">
					<a data-toggle="collapse" href="#personLinksPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Links</h2>
						</div>
					</a>
					<div id="personLinksPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							<ul class="list-unstyled">
								{foreach from=$links item=link}
									<li><a href="{$link.uri|escape}">{$link.title|escape}</a></li>
								{/foreach}
							</ul>
						</div>
					</div>
				</div>
			{/if}

			{include file="Archive2/panels/sharedRelatedPanels.tpl"}

		</div>
	</div>
	{include file="Archive2/panels/taxonomy_metadata_panel.tpl"}

	<script>
		Pika.Archive2.loadRelatedObjects('{$term_title|escape:"javascript"}');
		Pika.Archive2.loadTaxonomyExploreMore({$tid});
	</script>
{/strip}