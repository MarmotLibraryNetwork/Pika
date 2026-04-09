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
					style="max-width:300px; margin:0; float: left;">
			{/if}
		</div>
		<div class="col-xs-8">
			{include file="Archive2/partials/fieldRow.tpl" label="Given Name"    value=$given_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Middle Name"   value=$middle_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Family Name"   value=$family_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Maiden Name"   value=$maiden_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Alternate Name" value=$alternate_name}
			{include file="Archive2/partials/fieldRow.tpl" label="Birth Year"    value=$birth_year}
			{include file="Archive2/partials/fieldRow.tpl" label="Death Year"    value=$death_year}
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
	
	<div class="taxonomy-detail taxonomy-person">
		<div id="person-detail-accordion" class="panel-group">

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
			{if $military}
				<div class="panel" id="personMilitaryPanel">
					<a data-toggle="collapse" href="#personMilitaryPanelBody">
						<div class="panel-heading">
							<h2 class="panel-title">Military Service</h2>
						</div>
					</a>
					<div id="personMilitaryPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Branch"          value=$military.branch}
							{include file="Archive2/partials/fieldRow.tpl" label="Conflict"        value=$military.conflict}
							{include file="Archive2/partials/fieldRow.tpl" label="Rank"            value=$military.rank}
							{include file="Archive2/partials/fieldRow.tpl" label="Service Start"   value=$military.svc_start}
							{include file="Archive2/partials/fieldRow.tpl" label="Service End"     value=$military.svc_end}
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
							<h2 class="panel-title">Academic</h2>
						</div>
					</a>
					<div id="personAcademicPanelBody" class="panel-collapse collapse">
						<div class="panel-body">
							{include file="Archive2/partials/fieldRow.tpl" label="Position Title"    value=$academic.position_title}
							{include file="Archive2/partials/fieldRow.tpl" label="Degree"            value=$academic.degree_name}
							{include file="Archive2/partials/fieldRow.tpl" label="Discipline"        value=$academic.discipline}
							{include file="Archive2/partials/fieldRow.tpl" label="Graduation Date"   value=$academic.graduation_date}
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