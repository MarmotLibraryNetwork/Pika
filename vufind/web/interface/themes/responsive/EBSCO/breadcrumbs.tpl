{strip}
	<li>
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">EBSCO Research {translate text="Search Results"}</a>
			<span class="divider">&raquo;</span>
		{else}
			EBSCO Research
			<span class="divider">&raquo;</span>
		{/if}
	</li>
	{if $breadcrumbText}
		<li>
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
		</li>
	{/if}
{/strip}