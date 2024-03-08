{strip}
	{if $lastsearch}
		<li>
			&nbsp;
			<a href="{$lastsearch|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a>
			<span class="divider">&raquo;</span></li>
	{/if}
	{if $breadcrumbText}
		<li>
			&nbsp;
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $action == "Series"}
		<li>&nbsp;NoveList Series <span class="divider">&raquo;</span> <em>{$pageTitleShort}</em></li>
	{/if}
{/strip}