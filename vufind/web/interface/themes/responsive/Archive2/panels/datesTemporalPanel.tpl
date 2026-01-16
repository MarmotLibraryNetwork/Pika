{strip}
	<div class="panel" id="datesTemporalPanel"><a data-toggle="collapse" href="#datesTemporalPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Dates & Temporal Details</h2>
			</div>
		</a>
		<div id="datesTemporalPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Date Captured" value=$date_captured}
				{include file="Archive2/partials/fieldRow.tpl" label="Date Modified" value=$date_modified}
				{include file="Archive2/partials/fieldRow.tpl" label="EDTF Date" value=$edtf_date}
				{include file="Archive2/partials/fieldRow.tpl" label="EDTF Date Created" value=$edtf_date_created}
				{include file="Archive2/partials/fieldRow.tpl" label="EDTF Date Issued" value=$edtf_date_issued}
				{include file="Archive2/partials/fieldRow.tpl" label="Conference Date" value=$conference_date}
				{include file="Archive2/partials/fieldRow.tpl" label="Presented At" value=$presented_at}
				{include file="Archive2/partials/fieldRow.tpl" label="Publication Statement" value=$publication_s}
				{include file="Archive2/partials/fieldRow.tpl" label="Record Creation Date" value=$record_creation_date}
				{include file="Archive2/partials/fieldRow.tpl" label="Copyright Date" value=$copyright_date}
				{include file="Archive2/partials/fieldRow.tpl" label="Date Text" value=$date_text}
				{include file="Archive2/partials/fieldRow.tpl" label="Temporal Subject" value=$temporal_subject}
				{include file="Archive2/partials/fieldRow.tpl" label="Postmark" value=$postmark}
				{include file="Archive2/partials/fieldRow.tpl" label="Record Origin" value=$record_origin}
			</div>
		</div>
	</div>
{/strip}
