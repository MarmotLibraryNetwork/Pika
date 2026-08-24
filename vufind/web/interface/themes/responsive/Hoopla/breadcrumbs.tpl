{strip}
	{* Prefer the results url rebuilt from this record's own saved search over lastsearch, the
	   last search made anywhere in the session - see SearchObject_Solr::getNextPrevLinks().
	   Keep in step with GroupedWork/search-results-navigation.tpl, which links to the same place. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if $returnToSearchUrl}
		<li>
			{if $returnToSearchUrl}
				<a href="{$returnToSearchUrl|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a>
				<span class="divider">&raquo;</span>
			{else}
				Catalog
			{/if}
		</li>
	{/if}
	{if $recordDriver}
		<li>
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}" aria-current="page">{$recordDriver->getBreadcrumb()|truncate:30:"..."|escape}</a>
			<span class="divider">&raquo;</span>
		</li>
		{if $recordDriver->getFormats()}
			<li>
				&nbsp;<em>{implode subject=$recordDriver->getFormats() glue=", "}</em> <span class="divider">&raquo;</span>
			</li>
		{/if}
	{/if}

{/strip}