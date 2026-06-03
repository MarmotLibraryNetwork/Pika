{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Effective Date" value=$rights_effective_date isDate=true}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Expiration Date" value=$rights_expiration isDate=true}
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
	{if $rights_holder || $debugDetails}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Rights Holder: </div>
			<div class="result-value col-sm-8">
				{if $rights_holder}
					{foreach from=$rights_holder item=holder name=holderLoop}
						{if $holder.vocabulary eq 'corporate_body' && $holder.tid}
							<a href="/Archive2/Organization/{$holder.tid}">{$holder.name|escape}</a>
						{elseif $holder.vocabulary eq 'person' && $holder.tid}
							<a href="/Archive2/Person/{$holder.tid}">{$holder.name|escape}</a>
						{else}
							{$holder.name|escape}
						{/if}
						{if !$smarty.foreach.holderLoop.last}, <br>{/if}
					{/foreach}
				{else}
					<span class="text-muted">Not provided</span>
				{/if}
			</div>
		</div>
	{/if}
	{if $rights_creator || $debugDetails}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Rights Creator: </div>
			<div class="result-value col-sm-8">
				{if $rights_creator}
					{foreach from=$rights_creator item=creator name=creatorLoop}
						{if $creator.vocabulary eq 'corporate_body' && $creator.tid}
							<a href="/Archive2/Organization/{$creator.tid}">{$creator.name|escape}</a>
						{else}
							{$creator.name|escape}
						{/if}
						{if !$smarty.foreach.creatorLoop.last}, <br>{/if}
					{/foreach}
				{else}
					<span class="text-muted">Not provided</span>
				{/if}
			</div>
		</div>
	{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights" value=$rights}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights (Extended)" value=$rights_long}
	{include file="Archive2/partials/fieldRow.tpl" label="Rights Note" value=$rights_note}
{/strip}
