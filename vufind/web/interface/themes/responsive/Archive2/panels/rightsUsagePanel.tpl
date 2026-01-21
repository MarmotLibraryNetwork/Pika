{strip}
	<div class="panel" id="rightsUsagePanel"><a data-toggle="collapse" href="#rightsUsagePanelBody">
			<div class="panel-heading">
				<h2 class="panel-title">Rights & Usage</h2>
			</div>
		</a>
		<div id="rightsUsagePanelBody" class="panel-collapse collapse">
			<div class="panel-body">
				{include file="Archive2/partials/fieldRow.tpl" label="Rights" value=$rights}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Creator" value=$rights_creator}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Effective Date" value=$rights_effective_date}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Expiration" value=$rights_expiration}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Holder" value=$rights_holder}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights (Long)" value=$rights_long}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Note" value=$rights_note}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Organization Statement" value=$rights_org_statement}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement URI" value=$rights_org_statement.uri}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement Title" value=$rights_org_statement.title}
				{include file="Archive2/partials/fieldRow.tpl" label="Rights Statement Options" value=$rights_org_statement.options}
				{include file="Archive2/partials/fieldRow.tpl" label="Supporting Departments" value=$supporting_depts}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Usage" value=$pika_usage}
				{include file="Archive2/partials/fieldRow.tpl" label="Pika Access Limits" value=$pika_access_limits}
			</div>
		</div>
	</div>
{/strip}
