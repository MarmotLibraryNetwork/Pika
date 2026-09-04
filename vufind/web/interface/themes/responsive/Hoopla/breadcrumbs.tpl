{strip}
	{* Prefer the results url rebuilt from this record's own saved search over lastsearch, the
	   last search made anywhere in the session - see SearchObject_Solr::getNextPrevLinks().
	   Keep in step with GroupedWork/search-results-navigation.tpl, which links to the same place. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if $returnToSearchUrl}
		<li class="breadcrumb-item">
			{if $returnToSearchUrl}
				<a href="{$returnToSearchUrl|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a>
			{else}
				Catalog
			{/if}
		</li>
	{/if}
	{if $recordDriver}
		<li class="breadcrumb-item active" aria-current="page">
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}">{$recordDriver->getBreadcrumb()|truncate:30:"..."|escape}</a>
		</li>
		{if $recordDriver->getFormats()}
			<li class="breadcrumb-item">
				&nbsp;<em>{implode subject=$recordDriver->getFormats() glue=", "}</em>
			</li>
		{/if}
	{/if}
{/strip}