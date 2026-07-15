{if $lastsearch}{*TODO: last search should only be set if the last search was a genealogy search. Currently seems to be set for catalog searches*}
	<li class="breadcrumb-item">
		<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Search Results"}</a>
	</li>
{/if}
{if $breadcrumbText}
	<li class="breadcrumb-item active" aria-current="page">
		<em>{$breadcrumbText|truncate:30:"..."|escape}</em>
	</li>
{/if}

