{strip}
	{* Navigate search results from within the full record views *}

<div id="results-nav-fixed" class="results-nav-fixed">
	<div class="search-results-navigation{* text-center*}">
		<div id="previousRecordLink" class="previous">
			{if isset($previousId)}
				<a href="/{$previousType}/{$previousId|escape:"url"}?searchId={$searchId}&amp;recordIndex={$previousIndex}&amp;page={if isset($previousPage)}{$previousPage}{else}{$page}{/if}{if !empty($searchSource)}&amp;searchSource={$searchSource|escape:"url"}{/if}" title="{if !$previousTitle}{translate text='Previous'}{else}{$previousTitle|truncate:180:"..."|escape:'html'}{/if}">
					<span class="bi bi-chevron-left"></span> Prev
				</a>
			{/if}
		</div>
		<div id="returnToSearch" class="return">
			{* searchResultsUrl is rebuilt from the saved search this record was reached through
			   (SearchObject_Solr::getNextPrevLinks()), so it survives a search made in another tab.
			   lastsearch, the last search of the session, stands in where there is no saved search
			   to work from - a record opened outside a search, or a genealogy result, whose search
			   object does not assign searchResultsUrl. *}
			{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
			{if $returnToSearchUrl}
				<a href="{$returnToSearchUrl|escape}#record{$recordDriver->getUniqueId()|escape:"url"}">{translate text="Return to Search Results"}</a>
			{/if}
		</div>
		<div id="nextRecordLink" class="next">
			{if isset($nextId)}
				<a href="/{$nextType}/{$nextId|escape:"url"}?searchId={$searchId}&amp;recordIndex={$nextIndex}&amp;page={if isset($nextPage)}{$nextPage}{else}{$page}{/if}{if !empty($searchSource)}&amp;searchSource={$searchSource|escape:"url"}{/if}" title="{if !$nextTitle}{translate text='Next'}{else}{$nextTitle|truncate:180:"..."|escape:'html'}{/if}">
					Next <span class="bi bi-chevron-right"></span>
				</a>
			{/if}
		</div>
	</div>
</div>
{literal}
	<script>
		var results = document.getElementById("results-nav-fixed");
		var sticky = Pika.GroupedWork.getElementPosition(results);

		window.onscroll = function () {
			Pika.GroupedWork.staticPosition(sticky)
		};
	</script>
{/literal}
{/strip}