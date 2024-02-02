{strip}
{* Recommendations *}
{if $topRecommendations}
	{foreach from=$topRecommendations item="recommendations"}
		{include file=$recommendations}
	{/foreach}
{/if}

	<h2>{translate text='nohit_heading'}</h2>

	{* Search Replacement Term notice *}
	{include file="Search/search-replacementTerm-notice.tpl"}

	{if $leftTextSearchWarning}
		<div class="alert alert-warning">
				{$leftTextSearchWarning}
				{if $lookfor}
					<p>
						<br>
						<a href="/Search/Results?lookfor={$lookfor|truncate:35:''|escape:url}&basicType={$searchIndex|escape:url}" title="{$lookfor|truncate:35:''}">{$lookfor|truncate:35:''}</a>
					</p>
				{/if}
		</div>
	{/if}

	<p class="alert alert-info">{translate text='nohit_prefix'} <b>{if $lookfor}{$lookfor|escape:"html"}{else}&lt;empty&gt;{/if}</b> {translate text='nohit_suffix'}</p>

{* Return to Advanced Search Link *}
{if $searchType == 'advanced'}
	<h5>
		<a href="/Search/Advanced">Edit This Advanced Search</a>
	</h5>
{/if}

    {* Search Debugging *}
    {include file="Search/search-debug.tpl"}

	<div>
	{if $parseError}
		<div class="alert alert-danger">
			{$parseError}
		</div>
	{/if}

	{if $spellingSuggestions}
		<div class="correction">
			<h2>Spelling Suggestions</h2>
			<p>Here are some alternative spellings that you can try instead.</p>
			<div class="row">
				{foreach from=$spellingSuggestions item=url key=term name=termLoop}
					<div class="col-xs-6 col-sm-4 col-md-3 text-left">
						<a class='btn btn-xs btn-default btn-block' href="{$url|escape}">{$term|escape|truncate:25:'...'}</a>
					</div>
				{/foreach}
			</div>
		</div>
		<br>
	{/if}

	{if $searchSuggestions}
		<div id="searchSuggestions">
			<h2>Similar Searches</h2>
			<p>These searches are similar to the search you tried. Would you like to try one of these instead?</p>
			<div class="row">
				{foreach from=$searchSuggestions item=suggestion}
					<div class="col-xs-6 col-sm-4 col-md-3 text-left">
						<a class='btn btn-xs btn-default btn-block' href="/Search/Results?lookfor={$suggestion.phrase|escape:url}&basicType={$searchIndex|escape:url}" title="{$suggestion.phrase}">{$suggestion.phrase|truncate:25:'...'}</a>
					</div>
				{/foreach}
			</div>
		</div>
	{/if}

	{if $showExploreMoreBar}
		<div id="explore-more-bar-placeholder"></div>
		<script>
			$(function(){ldelim}
				Pika.Searches.loadExploreMoreBar('{$exploreMoreSection}', '{$exploreMoreSearchTerm|escape:"html"}');
			{rdelim});
		</script>
	{/if}

	{if $showProspectorLink}
		{* Prospector Results *}
		<div id='prospectorSearchResultsPlaceholder'></div>
		{* javascript call for content at bottom of page*}
	{elseif !empty($interLibraryLoanName) && !empty($interLibraryLoanUrl)}
		{include file="Search/interLibraryLoanSearch.tpl"}
	{/if}

	{if $showDplaLink}
		{* DPLA Results *}
		<div id='dplaSearchResultsPlaceholder'></div>
	{/if}

	{* Display Repeat this search links *}
	{if strlen($lookfor) > 0 && !empty($repeatSearchOptions)}
		<div class='repeatSearchHead'><h4>Try another catalog</h4></div>
			<div class='repeatSearchList'>
			{foreach from=$repeatSearchOptions item=repeatSearchOption}
				<div class='repeatSearchItem'>
					<a href="{$repeatSearchOption.link}" class='repeatSearchName' target='_blank'>{$repeatSearchOption.name}</a>{if $repeatSearchOption.description} - {$repeatSearchOption.description}{/if}
				</div>
			{/foreach}
		</div>
	{/if}

	{if $enableMaterialsRequest || $externalMaterialsRequestUrl}
		{include file="MaterialsRequest/solicit-new-materials-request.tpl"}
	{/if}

    {include file="Search/searchTools.tpl" showAdminTools=false}
</div>

<script>
	$(function(){ldelim}
		{if $showProspectorLink}
      {* Include slight delay to give time for the search to be saved into the database for retrieval here. See D-3592 *}
			setTimeout(function(){ldelim} Pika.Prospector.getProspectorResults(5, {$prospectorSavedSearchId}); {rdelim}, 237);
		{/if}
		{if $showDplaLink}
		Pika.DPLA.getDPLAResults('{$lookfor}');
		{/if}
		{rdelim});
</script>
{/strip}