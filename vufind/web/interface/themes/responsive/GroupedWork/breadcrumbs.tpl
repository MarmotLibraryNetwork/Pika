{strip}
	{if $lastsearch}
		<li class="breadcrumb-item">
			<a href="{$lastsearch|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a>
		</li>
	{/if}
	{if $breadcrumbText}
		<li class="breadcrumb-item">
			<a href="" aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</a>
		</li>
	{/if}
	{if $action == "Series"}
		<li class="breadcrumb-item">NoveList Series</li>
		<li class="breadcrumb-item"><em aria-current="page">{$pageTitleShort}</em></li>
	{/if}
{/strip}
