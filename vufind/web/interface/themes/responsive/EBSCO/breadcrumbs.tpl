{strip}
	<li class="breadcrumb-item">
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">EBSCO Research {translate text="Search Results"}</a>

		{else}
			EBSCO Research

		{/if}
	</li>
	{if $breadcrumbText}
		<li class="breadcrumb-item active" aria-current="page">
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em>
		</li>
	{/if}
{/strip}