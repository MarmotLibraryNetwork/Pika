{strip}
	<div class="panel" id="coreIdentificationPanel"><a data-toggle="collapse" href="#coreIdentificationPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Core Identification & Lifecycle</h2>
			</div>
		</a>
		<div id="coreIdentificationPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Node ID" value=$nid}
				{include file="Archive2/partials/fieldRow.tpl" label="UUID" value=$uuid}
				{include file="Archive2/partials/fieldRow.tpl" label="Version ID" value=$vid}
				{include file="Archive2/partials/fieldRow.tpl" label="Language Code" value=$langcode}
				{include file="Archive2/partials/fieldRow.tpl" label="Type" value=$type}
				{include file="Archive2/partials/fieldRow.tpl" label="Revision Timestamp" value=$revision_timestamp}
				{include file="Archive2/partials/fieldRow.tpl" label="Revision User ID" value=$revision_uid}
				{include file="Archive2/partials/fieldRow.tpl" label="Revision Log" value=$revision_log}
				{include file="Archive2/partials/fieldRow.tpl" label="Status" value=$status}
				{include file="Archive2/partials/fieldRow.tpl" label="Created On" value=$created}
				{include file="Archive2/partials/fieldRow.tpl" label="Changed On" value=$changed}
				{include file="Archive2/partials/fieldRow.tpl" label="Menu Link" value=$menu_link}
			</div>
		</div>
	</div>
{/strip}
