{strip}
	<li class="breadcrumb-item">
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">EBSCO Research {translate text="Search Results"}</a>

		{else}
			EBSCO Research

		{/if}
	</li>
	{if $breadcrumbText}
		<li class="breadcrumb-item">
			<em aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</em>
		</li>
	{/if}
{/strip}