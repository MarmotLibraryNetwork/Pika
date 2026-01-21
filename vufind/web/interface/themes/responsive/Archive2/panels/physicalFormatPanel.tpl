{strip}
	<div class="panel" id="physicalFormatPanel"><a data-toggle="collapse" href="#physicalFormatPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Physical & Format Details</h2>
			</div>
		</a>
		<div id="physicalFormatPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Extent" value=$extent}
				{include file="Archive2/partials/fieldRow.tpl" label="Material" value=$material}
				{include file="Archive2/partials/fieldRow.tpl" label="Materials" value=$materials}
				{include file="Archive2/partials/fieldRow.tpl" label="Measurement" value=$measurement}
				{include file="Archive2/partials/fieldRow.tpl" label="Physical Form" value=$physical_form}
				{include file="Archive2/partials/fieldRow.tpl" label="Style / Period" value=$style_period}
				{include file="Archive2/partials/fieldRow.tpl" label="Disc Number" value=$disc_number}
				{include file="Archive2/partials/fieldRow.tpl" label="Total Discs" value=$total_discs}
				{include file="Archive2/partials/fieldRow.tpl" label="Total Tracks" value=$total_tracks}
				{include file="Archive2/partials/fieldRow.tpl" label="Track Number" value=$track_number}
				{include file="Archive2/partials/fieldRow.tpl" label="Includes Stamp" value=$includes_stamp}
				{include file="Archive2/partials/fieldRow.tpl" label="System Details" value=$system_details}
				{include file="Archive2/partials/fieldRow.tpl" label="Digital Origin" value=$digital_origin}
				{include file="Archive2/partials/fieldRow.tpl" label="Mode of Issuance" value=$mode_of_issuance}
				{include file="Archive2/partials/fieldRow.tpl" label="Technique" value=$technique}
				{include file="Archive2/partials/fieldRow.tpl" label="Art Technique" value=$art_technique}
				{include file="Archive2/partials/fieldRow.tpl" label="Music Genre" value=$music_genre}
				{include file="Archive2/partials/fieldRow.tpl" label="Supporting Departments" value=$supporting_depts}
				{include file="Archive2/partials/fieldRow.tpl" label="Installations" value=$installations}
			</div>
		</div>
	</div>
{/strip}
