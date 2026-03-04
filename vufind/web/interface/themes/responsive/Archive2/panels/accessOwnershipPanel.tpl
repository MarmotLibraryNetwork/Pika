{strip}
	<div class="panel" id="accessOwnershipPanel"><a data-toggle="collapse" href="#accessOwnershipPanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Access & Ownership</h2>
			</div>
		</a>
		<div id="accessOwnershipPanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Access Restriction" value=$access_restriction}
				{include file="Archive2/partials/fieldRow.tpl" label="Access Terms" value=$access_terms}
				{include file="Archive2/partials/fieldRow.tpl" label="Owner ID" value=$owner_id}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Access Limits" value=$pika_access_limits}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Anonymous LC Download" value=$pika_anon_lc_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Anonymous Master Download" value=$pika_anon_master_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Master Download" value=$pika_master_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika LC Download" value=$pika_lc_download}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Claim Authorship" value=$pika_claim_authorship}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Show In Search" value=$pika_show_in_search}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Shown On Homepage" value=$pika_shown_homepage}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage" value=$pika_usage}
			</div>
		</div>
	</div>
{/strip}
