{strip}
	<div class="panel" id="notesContextPanel"><a data-toggle="collapse" href="#notesContextPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Notes & Contextual Metadata</h2>
			</div>
		</a>
		<div id="notesContextPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Acquisition Note" value=$acq_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Arrangement" value=$arrangement}
				{include file="Archive2/partials/fieldRow.tpl" label="Citation Notes" value=$citation_notes}
				{include file="Archive2/partials/fieldRow.tpl" label="Context Notes" value=$context_notes}
				{include file="Archive2/partials/fieldRow.tpl" label="Local Note" value=$local_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Funding Note" value=$funding_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Further Site Info" value=$further_site_info}
				{include file="Archive2/partials/fieldRow.tpl" label="Material Description" value=$material_description}
				{include file="Archive2/partials/fieldRow.tpl" label="Physical Description Note" value=$phys_desc_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Materials Note" value=$rel_materials_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Ownership Note" value=$ownership_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Reproduction Note" value=$repro_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Record Content Source" value=$record_content_source}
				{include file="Archive2/partials/fieldRow.tpl" label="Research Level" value=$research_level}
				{include file="Archive2/partials/fieldRow.tpl" label="Research Type" value=$research_type}
				{include file="Archive2/partials/fieldRow.tpl" label="History" value=$history}
				{include file="Archive2/partials/fieldRow.tpl" label="Display Hints" value=$display_hints}
			</div>
		</div>
	</div>
{/strip}
