{* Archive2 search results page.

   Archive2 has its own copy rather than sharing Archive/list.tpl (still used by Archive 1)
   because covers view here loads further results into the page instead of paging: covers
   mode never gets a pager assigned, so without the button below there is no way at all to
   reach past the first batch of results. *}
<div id="searchInfo">

	<h1 role="heading" aria-level="1" class="h2">Archive Search Results</h1>

	{* Recommendations *}
	{if $topRecommendations}
		{foreach from=$topRecommendations item="recommendations"}
			{include file=$recommendations}
		{/foreach}
	{/if}

	{* Listing Options *}
	<div class="result-head">
		{if $recordCount}
			{translate text="Showing"}
			<b> {$recordStart}</b> - <b>{$recordEnd} </b>
			{translate text='of'} <b>{$recordCount} </b>
			{if $searchType == 'basic'}{translate text='for search'}: <b>'{$lookfor|escape:"html"}'</b>,{/if}
		{/if}
		<span>
			,&nbsp;{translate text='query time'}: {$qtime}s
		</span>

		{* Search Debugging *}
		{include file="Search/search-debug.tpl"}


		{if $spellingSuggestions}
			<br><br><div class="correction"><strong>{translate text='spell_suggest'}</strong>:<br>
			{foreach from=$spellingSuggestions item=details key=term name=termLoop}
				{$term|escape} &raquo; {foreach from=$details.suggestions item=data key=word name=suggestLoop}<a href="{$data.replace_url|escape}">{$word|escape}</a>{if $data.expand_url} <a href="{$data.expand_url|escape}"><img src="/images/silk/expand.png" alt="{translate text='spell_expand_alt'}"></a> {/if}{if !$smarty.foreach.suggestLoop.last}, {/if}{/foreach}{if !$smarty.foreach.termLoop.last}<br>{/if}
			{/foreach}
			</div>
		{/if}

		{* User's viewing mode toggle switch *}
		{include file="Search/results-displayMode-toggle.tpl"}

		<div class="clearer"></div>
	</div>
	{* End Listing Options *}

	{if $subpage}
		{include file=$subpage}
	{else}
		{$pageContent}
	{/if}

	{if $displayMode == 'covers'}
		{* Covers view appends batches to the grid rather than paging.  $recordEnd is the last
		   result on screen, so this hides the button on the final batch; getMoreResults()
		   hides it again when the server reports it has served the last page, which covers
		   the case of the count changing between requests. *}
		{if $recordEnd < $recordCount}
			<button type="button" id="more-browse-results" onclick="return Pika.Archive2.getMoreResults()" aria-label="Load more search results">
				<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
			</button>
		{/if}
		{* Appending tiles is a silent change to a screen reader, so announce each batch. *}
		<div id="more-results-status" class="sr-only" aria-live="polite"></div>
	{else}
		{if $pageLinks.all}<div class="pagination">{$pageLinks.all}</div>{/if}
	{/if}

   {include file="Search/searchTools.tpl" showAdminTools=$showAdminTools|default:false}
</div>
{* Embedded Javascript For this Page *}
<script>
	$(function(){ldelim}
		if ($('#horizontal-menu-bar-container').is(':visible')) {ldelim}
			$('#home-page-search').show();  {*// Always show the searchbox for search results in mobile views.*}
			{rdelim}

		{* Because content is served on the page, have to set the mode that was used, even if the user didn't choose the mode. *}
		Pika.Searches.displayMode = '{$displayMode}';
		{if $onInternalIP}
		Globals.opac = 1; {* set to true to keep opac browsers from storing browse mode *}
		{/if}
		$('#'+Pika.Searches.displayMode).addClass('active'); {* show user which one is selected *}

		{* Start counting from the page the server actually rendered.  Covers view has no
		   pager, but a patron can still land on a later page from a bookmark or from the
		   "back to results" link on an object or term page, and starting at 1 there would
		   request a batch that is already on screen.  Results.php guarantees an integer. *}
		Pika.Archive2.curPage = {$page};

		{rdelim});
</script>
