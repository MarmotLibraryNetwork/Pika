{strip}
	<div class="panel" id="researchSpecializedPanel"><a data-toggle="collapse" href="#researchSpecializedPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Research & Specialized Data</h2>
			</div>
		</a>
		<div id="researchSpecializedPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Action Note" value=$action_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Arrangement" value=$arrangement}
				{include file="Archive2/partials/fieldRow.tpl" label="Context Notes" value=$context_notes}
				{include file="Archive2/partials/fieldRow.tpl" label="Citation Notes" value=$citation_notes}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Discipline" value=$degree_discipline}
				{include file="Archive2/partials/fieldRow.tpl" label="Degree Name" value=$degree_name}
				{include file="Archive2/partials/fieldRow.tpl" label="Research Level" value=$research_level}
				{include file="Archive2/partials/fieldRow.tpl" label="Research Type" value=$research_type}
				{include file="Archive2/partials/fieldRow.tpl" label="Real Estate Data" value=$real_estate_data}
				{include file="Archive2/partials/fieldRow.tpl" label="Music Genre" value=$music_genre}
				{include file="Archive2/partials/fieldRow.tpl" label="Supporting Departments" value=$supporting_depts}
				{include file="Archive2/partials/fieldRow.tpl" label="Presented At" value=$presented_at}
			</div>
		</div>
	</div>
{/strip}
