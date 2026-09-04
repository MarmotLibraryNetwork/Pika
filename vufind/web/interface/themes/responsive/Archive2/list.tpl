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
		{* Feedback while a batch is on its way.  Fetching one is slow enough that without this
		   the button reads as not having registered the click.  The spinner is the visible
		   half and #more-results-status carries the same news to a screen reader; they are
		   kept apart so the live region announces its own text only, not the image markup.
		   The image is decorative here - the words beside it already say what it means.

		   Centered on the button below it, which is full width and centers its own chevron.
		   The image is 32px against a line of text, so it needs aligning to the middle of
		   that line rather than sitting on its baseline for the two to read as one unit. *}
		<div id="more-results-loading" class="d-none text-center" style="margin: 10px 0;">
			<img src="{img filename='loading.gif'}" alt="" style="vertical-align: middle; margin-right: 5px;">
			{translate text="Loading"}...
		</div>

		{* Covers view appends batches to the grid rather than paging.  $recordEnd is the last
		   result on screen, so this hides the button on the final batch; getMoreResults()
		   hides it again when the server reports it has served the last page, which covers
		   the case of the count changing between requests. *}
		{if $recordEnd < $recordCount}
			<button type="button" id="more-browse-results" onclick="return Pika.Archive2.getMoreResults()" aria-label="Load more search results">
				<span class="bi bi-chevron-down" aria-hidden="true"></span>
			</button>
		{/if}
		<div id="more-results-status" class="visually-hidden" aria-live="polite"></div>
	{else}
		{if $pageLinks.all}<nav aria-label="{translate text='Search results pages'}">{$pageLinks.all}</nav>{/if}
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
