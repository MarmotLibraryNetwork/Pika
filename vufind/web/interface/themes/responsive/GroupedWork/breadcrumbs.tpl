{strip}
	{if $lastsearch}
		<li class="breadcrumb-item">
			<a href="{$lastsearch|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a>
		</li>
	{/if}
	{if $breadcrumbText}
		<li class="breadcrumb-item active" aria-current="page">
			<a href="">{$breadcrumbText|truncate:30:"..."|escape}</a>
		</li>
	{/if}
	{if $action == "Series"}
		<li class="breadcrumb-item">NoveList Series</li>
		<li class="breadcrumb-item active" aria-current="page"><em>{$pageTitleShort}</em></li>
	{/if}
{/strip}
