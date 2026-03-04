{strip}
	{if isset($value) || $debug}
	<div class="row archive-field-row">
		<div class="result-label col-sm-4">{$label}: </div>
		<div class="result-value col-sm-8">
			{if isset($value)}
				{include file="Archive2/partials/renderValue.tpl" value=$value}
			{else}
				<span class="text-muted">Not provided</span>
			{/if}
		</div>
	</div>
	{/if}
{/strip}
