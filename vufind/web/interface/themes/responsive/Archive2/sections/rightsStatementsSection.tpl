{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights" value=$rights}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights (Extended)" value=$rights_long}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Note" value=$rights_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Holder" value=$rights_holder}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Creator" value=$rights_creator}
	{include file="Archive2/partials/fieldRow.tpl" label="Effective Date" value=$rights_effective_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Expiration Date" value=$rights_expiration}
	{if $rights_org_statement.title}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Rights Statement:</div>
			<div class="result-value col-sm-8">
				{if $rights_org_statement.uri}
					<a href="{$rights_org_statement.uri|escape}" target="_blank">{$rights_org_statement.title|escape}</a>
				{else}
					{$rights_org_statement.title|escape}
				{/if}
			</div>
		</div>
	{/if}
{/strip}
