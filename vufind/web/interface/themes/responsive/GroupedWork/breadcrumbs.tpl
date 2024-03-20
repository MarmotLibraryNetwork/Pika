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
			<em aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $action == "Series"}
		<li>&nbsp;NoveList Series <span class="divider">&raquo;</span> <em aria-current="page">{$pageTitleShort}</em></li>
	{/if}
{/strip}