{* searchResultsUrl is rebuilt from the saved search this person was reached through (see
   SearchObject_Genealogy::getNextPrevLinks()), so it survives a search made in another tab and
   works for a link that was shared or bookmarked.  lastsearch, the last search of the session
   whatever index it was made against, stands in where there is no saved search to work from.
   Kept in step with GroupedWork/search-results-navigation.tpl, which links back to the same
   results from the navigation bar on this page. *}
{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
{if $returnToSearchUrl}
	<li>
		<a href="{$returnToSearchUrl|escape}#record{$recordDriver->getUniqueId()|escape:"url"}">{translate text="Search Results"}</a> <span class="divider">&raquo;</span>
	</li>
{/if}
{if $breadcrumbText}
	<li>
		<em aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
	</li>
{/if}

