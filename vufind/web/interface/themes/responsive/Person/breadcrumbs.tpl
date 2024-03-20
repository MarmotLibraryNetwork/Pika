{if $lastsearch}{*TODO: last search should only be set if the last search was a genealogy search. Currently seems to be set for catalog searches*}
	<li>
		<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Search Results"}</a> <span class="divider">&raquo;</span>
	</li>
{/if}
{if $breadcrumbText}
	<li>
		<em aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
	</li>
{/if}

