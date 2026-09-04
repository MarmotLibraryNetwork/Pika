<div id="searchInfo">
	<h1 role="heading" aria-level="1" class="h2">Search Results</h1>

		{if $searchType == 'advanced'}
			<div id="advanced-search" class="card">
			<div class="card-body p-2">
{*				<h5>Advanced Search Query : </h5>*}
				<code id="advanced-search-display-query">{$lookfor|escape:"html"}</code>
				<br>
				<div class="form-text">
				<a href="/Search/Advanced">{translate text='Edit This Advanced Search'}</a>
				</div>
			</div>
			</div>
		{/if}


	{* Recommendations *}
	{if $topRecommendations}
		{foreach from=$topRecommendations item="recommendations"}
			{include file=$recommendations}
		{/foreach}
	{/if}

		{* Search Replacement Term notice *}
		{include file="Search/search-replacementTerm-notice.tpl"}

    {* Information about the search *}
	<div class="result-head">

		<div>
			{if $recordCount}
				{if $displayMode == 'covers'}
					There are {$recordCount|number_format} total results.
				{else}
					{translate text="Showing"}
					{$recordStart} - {$recordEnd}
					{translate text='of'} {$recordCount|number_format}
				{/if}
			{else}
				No results found in {$sectionLabel}
			{/if}
			<span>
			 {translate text='query time'}: {$qtime}s
			</span>
		</div>

		{* Search Debugging *}
		{include file="Search/search-debug.tpl"}

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

		{if $recordEnd < $recordCount}
			<button type="button" id="more-browse-results" onclick="return Pika.Searches.getMoreResults()" aria-label="Load more search results">
				<span class="bi bi-chevron-down" aria-hidden="true"></span>
			</button>
		{/if}
		<div id="more-results-status" class="visually-hidden" aria-live="polite"></div>
	{else}
		{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
	{/if}

	{*Additional Suggestions on the last page of search results or no results returned *}

	{if $showProspectorLink}
		{* Prospector Results *}
		<div id="prospectorSearchResultsPlaceholder"></div>
		{* javascript call for content at bottom of page*}
	{elseif !empty($interLibraryLoanName) && !empty($interLibraryLoanUrl)}
		{include file="Search/interLibraryLoanSearch.tpl"}
	{/if}

	{if $showDplaLink}
		{* DPLA Results *}
		<div id="dplaSearchResultsPlaceholder"></div>
	{/if}

	{if $enableMaterialsRequest || $externalMaterialsRequestUrl}
		{include file="MaterialsRequest/solicit-new-materials-request.tpl"}
	{/if}

	{include file="Search/searchTools.tpl" showAdminTools=true}
</div>

{* Embedded Javascript For this Page *}
<script>
	$(function(){ldelim}
		if ($('#horizontal-menu-bar-container').is(':visible')) {ldelim}
			$('#home-page-search').show();  {*// Always show the searchbox for search results in mobile views.*}
		{rdelim}

		{if $showProspectorLink}
			{* Include slight delay to give time for the search to be saved into the database for retrieval here. See D-3592 *}
			setTimeout(function(){ldelim} Pika.Prospector.getProspectorResults(5, {$prospectorSavedSearchId}); {rdelim}, 237);
		{/if}

		{if $showDplaLink}
		Pika.DPLA.getDPLAResults('{$lookfor}');
		{/if}

		{*{include file="Search/results-displayMode-js.tpl"}*}
		{if !$onInternalIP}
		{*if (!Globals.opac &&Pika.hasLocalStorage()){ldelim}*}
			{*var temp = window.localStorage.getItem('searchResultsDisplayMode');*}
			{*if (Pika.Searches.displayModeClasses.hasOwnProperty(temp)) Pika.Searches.displayMode = temp; *}{* if stored value is empty or a bad value, fall back on default setting ("null" returned when not set) *}
			{*else Pika.Searches.displayMode = '{$displayMode}';*}
			{*{rdelim}*}
		{*else*}
		{* Because content is served on the page, have to set the mode that was used, even if the user didn't choose the mode. *}
			Pika.Searches.displayMode = '{$displayMode}';
		{else}
			Pika.Searches.displayMode = '{$displayMode}';
			Globals.opac = 1; {* set to true to keep opac browsers from storing browse mode *}
		{/if}
		$('#'+Pika.Searches.displayMode).addClass('active'); {* show user which one is selected *}

		{* Start counting from the page the server actually rendered.  Covers view has no pager,
		   but a patron can still land on a later page from a bookmark or a shared link, and
		   starting at 1 there would request a batch that is already on screen.  Results.php
		   validates this as a digit string, and sets covers view to the same page size of 24
		   the load-more call uses, so the arithmetic lines up. *}
		Pika.Searches.curPage = {$page};

		{rdelim});
</script>