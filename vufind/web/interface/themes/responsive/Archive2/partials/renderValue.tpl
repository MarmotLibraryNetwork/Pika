{strip}
	{if is_array($value)}
		{if $value|@count > 0}
			<dl class="archive-field-values list-unstyled">
				{foreach from=$value key=subKey item=subValue}
					<dt>{$subKey|replace:'_':' '|capitalize}</dt>
					<dd>{include file="Archive2/partials/renderValue.tpl" value=$subValue}</dd>
				{/foreach}
			</dl>
		{else}
			<span class="text-muted">Not provided</span>
		{/if}
	{else}
		{if $value ne '' && $value ne null}
			{$value}
		{else}
			<span class="text-muted">Not provided</span>
		{/if}
	{/if}
{/strip}
