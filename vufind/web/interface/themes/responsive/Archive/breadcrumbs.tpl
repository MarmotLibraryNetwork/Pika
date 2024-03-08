{strip}
	<li>
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Archive Search Results"}</a>
			<span class="divider">&raquo;</span>
		{else}
			<a href="/Archive/Home">Local Digital Archive</a>
			<span class="divider">&raquo;</span>
		{/if}
	</li>
	<li>
		{if $breadcrumbText}
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em>
			<span class="divider">&raquo;</span>
		{/if}
	</li>
{/strip}