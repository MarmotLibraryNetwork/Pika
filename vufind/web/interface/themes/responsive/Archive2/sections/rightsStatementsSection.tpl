{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights" value=$rights}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights (Extended)" value=$rights_long}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Note" value=$rights_note}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Holder" value=$rights_holder}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Creator" value=$rights_creator}
	{include file="Archive2/partials/fieldRow.tpl" label="Effective Date" value=$rights_effective_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Expiration Date" value=$rights_expiration}
	<div class="row archive-field-row">
		<div class="result-label col-sm-4">Rights Statement:</div>
		<div class="result-value col-sm-8">
			{if $rights_org_statement.uri}
				<a href="{$rights_org_statement.uri|escape}" target="_blank">{translate text=$rights_org_statement.uri}</a>
			{elseif $rights_org_statement.title}
				{$rights_org_statement.title|escape}
			{else}
				<a href="http://rightsstatements.org/page/CNE/1.0/?language=en" target="_blank">{translate text="http://rightsstatements.org/page/CNE/1.0/?language=en"}</a>
			{/if}
		</div>
	</div>
{/strip}
