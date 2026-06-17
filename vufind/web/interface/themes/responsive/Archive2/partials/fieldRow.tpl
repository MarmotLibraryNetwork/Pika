{strip}
	{assign var="hasValue" value=false}
	{if isset($value)}
		{if is_array($value)}
			{if $value|@count > 0}{assign var="hasValue" value=true}{/if}
		{elseif $value ne '' && $value ne null}
			{assign var="hasValue" value=true}
		{/if}
	{/if}
	{if $hasValue || $debugDetails}
	<div class="row archive-field-row">
		<div class="result-label col-xs-4">{$label}: </div>
		<div class="result-value col-xs-8">
			{if $hasValue}
				{include file="Archive2/partials/renderValue.tpl" value=$value}
			{else}
				<span class="text-muted">Not provided</span>
			{/if}
		</div>
	</div>
	{/if}
{/strip}