{strip}
	{if is_array($value)}
		{if $value|@count > 0}
			<dl class="archive-field-values list-unstyled">
				{foreach from=$value key=subKey item=subValue}
					{if is_numeric($subKey)}
						<div>{include file="Archive2/partials/renderValue.tpl" value=$subValue}</div>
					{else}
						<dt>{$subKey|replace:'_':' '|capitalize}</dt>
						<dd>{include file="Archive2/partials/renderValue.tpl" value=$subValue}</dd>
					{/if}
				{/foreach}
			</dl>
		{else}
			<span class="text-muted">Not provided</span>
		{/if}
	{else}
		{if $value ne '' && $value ne null}
			{if $isDate}
				{if $value|@strlen == 4 && $value|@is_numeric}
					{$value}
				{else}
					{$value|date_format:"%B %e, %Y"}
				{/if}
			{else}
				{$value}
			{/if}
		{else}
			<span class="text-muted">Not provided</span>
		{/if}
	{/if}
{/strip}
