{strip}
	<li class="breadcrumb-item">
		{if $lastsearch}
			<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a>
		{else}
			Catalog
		{/if}
	</li>
	{if $recordDriver}
		<li class="breadcrumb-item">
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}" aria-current="page">{$recordDriver->getBreadcrumb()|truncate:30:"..."|escape}</a>
		</li>
		<li class="breadcrumb-item">
			<em>{$groupedWorkDriver->getFormatCategory()}</em>
		</li>
	{/if}
{/strip}
