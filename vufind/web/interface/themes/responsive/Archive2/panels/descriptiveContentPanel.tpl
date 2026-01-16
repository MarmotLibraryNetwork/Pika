{strip}
	<div class="panel" id="descriptiveContentPanel"><a data-toggle="collapse" href="#descriptiveContentPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Descriptive Content</h2>
			</div>
		</a>
		<div id="descriptiveContentPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Description" value=$description}
				{include file="Archive2/partials/fieldRow.tpl" label="Extended Description" value=$description_long}
				{include file="Archive2/partials/fieldRow.tpl" label="Context Notes" value=$context_notes}
				{include file="Archive2/partials/fieldRow.tpl" label="History" value=$history}
				{include file="Archive2/partials/fieldRow.tpl" label="General Note" value=$note}
				{include file="Archive2/partials/fieldRow.tpl" label="Local Note" value=$local_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Material Description" value=$material_description}
				{include file="Archive2/partials/fieldRow.tpl" label="Physical Description Note" value=$phys_desc_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Related Materials Note" value=$rel_materials_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Reproduction Note" value=$repro_note}
				{include file="Archive2/partials/fieldRow.tpl" label="TOC Summary" value=$toc_summary}
				{include file="Archive2/partials/fieldRow.tpl" label="Table of Contents" value=$table_of_contents}
				{include file="Archive2/partials/fieldRow.tpl" label="Transcription Location" value=$transcription_loc}
			</div>
		</div>
	</div>
{/strip}
