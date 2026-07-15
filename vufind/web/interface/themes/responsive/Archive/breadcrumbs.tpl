{strip}
	<li class="breadcrumb-item">
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Archive Search Results"}</a>

		{else}
			<a href="/Archive/Home">Local Digital Archive</a>

		{/if}
	</li>
	<li class="breadcrumb-item active" aria-current="page">
		{if $breadcrumbText}
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em>

		{/if}
	</li>
{/strip}