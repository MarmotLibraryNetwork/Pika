
<div id="searchInfo">

		{if $searchType == 'advanced'}
			<div id="advanced-search" class="well well-sm">
{*				<h5>Advanced Search Query : </h5>*}
				<code id="advanced-search-display-query">{$lookfor|escape:"html"}</code>
				<br>
				<div class="help-block">
				<a href="/Search/Advanced">{translate text='Edit This Advanced Search'}</a>
				</div>
			</div>
		{/if}


	{* Recommendations *}
	{if $topRecommendations}
		{foreach from=$topRecommendations item="recommendations"}
			{include file=$recommendations}
		{/foreach}
	{/if}
{include file="Search/bookbag.tpl"}

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
		{if $recordEnd < $recordCount}
			<a onclick="return Pika.Searches.getMoreResults()" role="button">
				<div class="row" id="more-browse-results">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</div>
			</a>
		{/if}
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
	$('.checkbox-results').change(function(){ldelim}
				Pika.GroupedWork.showBookbag(this);
			{rdelim});
	$('.bookbag').click(function(){ldelim}
			Pika.GroupedWork.openBookbag(this);
	{rdelim});
	$('body').on('click', 'span.remove', function(){ldelim}
			var checkedId = this.id.replace(/remove_/g, 'select_');
			if($("#"  + checkedId +":checked")){ldelim}
						$("#"+ checkedId).prop("checked", false);
						Pika.GroupedWork.showBookbag(this);
					{rdelim};
			{rdelim});
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
		$('#'+Pika.Searches.displayMode).parent('label').addClass('active'); {* show user which one is selected *}

		{rdelim});
</script>