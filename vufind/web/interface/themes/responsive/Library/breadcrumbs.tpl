{if $lastsearch}
	<li class="breadcrumb-item"><a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a></li>
{/if}
{if $breadcrumbText}
	<li class="breadcrumb-item"><em>{$breadcrumbText|truncate:30:"..."|escape}</em></li>
{/if}

