{strip}
	{* Prefer the results url rebuilt from this record's own saved search over lastsearch, the
	   last search made anywhere in the session - see SearchObject_Solr::getNextPrevLinks().
	   Keep in step with GroupedWork/search-results-navigation.tpl, which links to the same place. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if $returnToSearchUrl}
		<li class="breadcrumb-item">
			<a href="{$returnToSearchUrl|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a>
		</li>
	{/if}
	{if $breadcrumbText}
		<li class="breadcrumb-item active" aria-current="page">
			<a href="">{$breadcrumbText|truncate:30:"..."|escape}</a>
		</li>
	{/if}
	{if $action == "Series"}
		<li class="breadcrumb-item">NoveList Series</li>
		<li class="breadcrumb-item active" aria-current="page"><em>{$pageTitleShort}</em></li>
	{/if}
{/strip}
