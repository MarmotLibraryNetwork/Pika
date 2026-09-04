{strip}
	{* Navigate archive search results from within the full record & taxonomy term views.
	   All of these variables are assigned by SearchObject_Islandora2::getNextPrevLinks().
	   The previous/next links carry the saved search on to the record they point at, so the
	   navigation survives from one record to the next.

	   searchResultsUrl is rebuilt from that saved search; lastsearch, the last archive search
	   of the session, stands in when there is no saved search to work from - a record reached
	   from somewhere other than the search results. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if isset($previousUrl) || isset($nextUrl) || $returnToSearchUrl}
		<nav class="search-results-navigation" aria-label="{translate text='Search results navigation'}">
			<div id="previousRecordLink" class="previous">
				{if isset($previousUrl)}
					<a href="{$previousUrl}?searchId={$searchId}&amp;recordIndex={$previousIndex}&amp;page={if isset($previousPage)}{$previousPage}{else}{$page}{/if}" title="{if !$previousTitle}{translate text='Previous'}{else}{$previousTitle|truncate:180:"..."|escape:'html'}{/if}">
						<span class="bi bi-chevron-left" aria-hidden="true"></span> {translate text='Prev'}
					</a>
				{/if}
			</div>
			<div id="returnToSearch" class="return">
				{if $returnToSearchUrl}
					<a href="{$returnToSearchUrl|escape}">{translate text="Return to Search Results"}</a>
				{/if}
			</div>
			<div id="nextRecordLink" class="next">
				{if isset($nextUrl)}
					<a href="{$nextUrl}?searchId={$searchId}&amp;recordIndex={$nextIndex}&amp;page={if isset($nextPage)}{$nextPage}{else}{$page}{/if}" title="{if !$nextTitle}{translate text='Next'}{else}{$nextTitle|truncate:180:"..."|escape:'html'}{/if}">
						{translate text='Next'} <span class="bi bi-chevron-right" aria-hidden="true"></span>
					</a>
				{/if}
			</div>
		</nav>
	{/if}
{/strip}
