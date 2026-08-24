{strip}
	{* Prefer the results url rebuilt from this record's own saved search over lastsearch, the
	   last search made anywhere in the session - see SearchObject_Solr::getNextPrevLinks().
	   Keep in step with GroupedWork/search-results-navigation.tpl, which links to the same place. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if $returnToSearchUrl}
		<li>
			&nbsp;
			<a href="{$returnToSearchUrl|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a>
			<span class="divider">&raquo;</span></li>
	{/if}
	{if $breadcrumbText}
		<li>
			&nbsp;
			<a href="" aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</a> <span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $action == "Series"}
		<li>&nbsp;NoveList Series <span class="divider">&raquo;</span> <em aria-current="page">{$pageTitleShort}</em></li>
	{/if}
{/strip}