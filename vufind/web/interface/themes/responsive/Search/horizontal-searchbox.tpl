{strip}
<div id="horizontal-search-box" class="row">
	<form method="get" action="/Union/Search" id="searchForm" class="form-inline" onsubmit="Pika.Searches.processSearchForm();">

		{* Hidden Inputs *}

{* TODO: Can't tell why this is needed and it creates messier URLs for users
		{if $searchIndex == 'Keyword' || $searchIndex == '' || $searchIndex == 'GenealogyKeyword'}
			<input type="hidden" name="basicType" id="basicType" value="">
			<input type="hidden" name="genealogyType" id="genealogyType" value="">
		{/if}*}
		<input type="hidden" name="view" id="view" value="{$displayMode}">

		{if isset($showCovers)}
			<input type="hidden" name="showCovers"{* id="showCovers"*} value="{if $showCovers}on{else}off{/if}">
		{/if}

		{assign var="hiddenSearchSource" value=false}
		{* Switch sizing when no search source is to be displayed *}
		{if !empty($searchSources) && $searchSources|@count <= 1 && !empty($searchSources[$searchSource])}
			{assign var="hiddenSearchSource" value=true}
			<input type="hidden" name="searchSource" value="{$searchSource}">
			{assign var="archiveOnly" value=false}
			{if $searchSource == "islandora"}
				{assign var="archiveOnly" value=true}
				{assign var="hiddenSearchSource" value=false}
			{/if}
		{/if}

		<div class="col-sm-9 col-xs-12">
			<div class="row">
				<div class="col-lg-1 col-md-2 col-sm-2 col-xs-12">
					<label id="horizontal-search-label" for="lookfor"{* class=""*}>{translate text="Search for"} </label>
				</div>
				<div class="
				{if $hiddenSearchSource}
				col-lg-9 col-md-8
				{else}
				col-lg-6 col-md-5
				{/if} col-sm-10 col-xs-12">
					{* Main Search Term Box *}
					<textarea class="form-control"{/strip}
							          id="lookfor"
							          placeholder="&#128269; SEARCH" {* disabled in css by default. plb 11-19-2014 *}
{*							          type="search"*}
							          name="lookfor"
{*							          value=""*}
							          title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term."
							          onkeyup="return Pika.Searches.resetSearchType()"
							          onfocus="$(this).trigger('select')" {* Select/highlight inputted text *}
							          autocomplete="off"
							          rows="1"
											{strip}>
								{if $searchType != 'advanced'}{$lookfor|escape:"html"}{/if}
					</textarea>
				</div>
				{* Search Type *}
				<div class="col-lg-2 col-lg-offset-0 col-md-2 col-md-offset-0 {if $hiddenSearchSource}
				col-sm-10 col-sm-offset-2 col-xs-12 col-xs-offset-0
				{else}
				col-sm-3 col-sm-offset-4 col-xs-5 col-xs-offset-0
				{/if}">
				{if $archiveOnly}
					<input type="hidden" name="basicType" id="basicSearchTypes" value="Keyword" title="Search by Keyword to find subjects, titles, authors, etc. Search by Title or Author for more precise results.">
				{else}
					<select name="basicType" aria-label="Type of catalog search" class="searchTypeHorizontal form-control catalogType" id="basicSearchTypes" title="Search by Keyword to find subjects, titles, authors, etc. Search by Title or Author for more precise results." {if $searchSource == 'genealogy' || $searchSource == 'islandora'}style="display:none"{/if}>
						{foreach from=$basicSearchTypes item=searchDesc key=searchVal}
							<option value="{$searchVal}"{if $basicSearchIndex == $searchVal || $searchIndex == $searchVal} selected="selected"{/if}>by {translate text=$searchDesc}</option>
						{/foreach}
					</select>

					{*TODO: How to chose the Genealogy Search type initially *}
					<select name="genealogyType" aria-label="Type of genealogy search" class="searchTypeHorizontal form-control genealogyType" id="genealogySearchTypes" {if $searchSource != 'genealogy'}style="display:none"{/if}>
						{foreach from=$genealogySearchTypes item=searchDesc key=searchVal}
							<option value="{$searchVal}"{if $genealogySearchIndex == $searchVal} selected="selected"{/if}>{translate text=$searchDesc}</option>
						{/foreach}
					</select>
				{/if}
				</div>

					{if !$hiddenSearchSource}
						<div class="col-lg-3 col-md-3 col-sm-5 col-xs-7">
							<select name="searchSource" id="searchSource" aria-label="Select search source" title="Select what to search.	Items marked with a * will redirect you to one of our partner sites." onchange="Pika.Searches.enableSearchTypes();" class="searchSourceHorizontal form-control">
								{foreach from=$searchSources item=searchOption key=searchKey}
									<option data-catalog_type="{$searchOption.catalogType}" value="{$searchKey}"
											{if $searchKey == $searchSource} selected="selected"{/if}
											{if $searchKey == $searchSource} id="default_search_type"{/if}
											   {*leading space for clean parsing*} title="{$searchOption.description}">
										{translate text="in"} {$searchOption.name}{if $searchOption.external} *{/if}
									</option>
								{/foreach}
							</select>
						</div>
					{/if}


			</div>
		</div>

		{* GO Button & Search Links*}
		<div id="horizontal-search-button-container" class="col-sm-3 col-xs-12">
			<div class="row">
				<div class="col-tn-3 col-xs-3 col-sm-4 col-md-4">
					<button class="btn btn-default" type="submit">
						<span class="glyphicon glyphicon-search" aria-hidden="true"></span>
						<span id="horizontal-search-box-submit-text">&nbsp;GO</span>
						{*<span class="visible-xs-inline"> SEARCH</span>  TODO: Will work when upgraded to Bootstrap 3.0*}
					</button>
				</div>

				<div id="horizontal-search-additional" class="col-tn-5 col-xs-5 col-sm-12 col-md-8">
					{* Keep Applied Filters Checkbox *}
					{if $filterList && $action != 'MyList'}
						<label for="keepFiltersSwitch" id="keepFiltersSwitchLabel">
							<input id="keepFiltersSwitch" type="checkbox" onclick="Pika.Searches.filterAll(this);"> Keep Applied Filters</label>
					{/if}

					{* Return to Advanced Search Link *}
					{if $searchType == 'advanced'}
						<div>
							&nbsp;
							<a id="advancedSearchLink" href="/Search/Advanced">{translate text='Edit This Advanced Search'}</a>
						</div>

						{* Show Advanced Search Link *}
						{elseif $showAdvancedSearchbox}
						<div>
							&nbsp;
							<a id="advancedSearchLink" href="/Search/Advanced">{translate text='Advanced Search'}</a>
						</div>
					{/if}
				</div>

				{* Show/Hide Search Facets & Sort Options *}
				{if $recordCount || $sideRecommendations}
					<div class="col-tn-3 col-xs-3 visible-xs">
						<a class="btn btn-default" id="refineSearchButton" role="button" onclick="Pika.Menu.Mobile.showSearchFacets()">{translate text="Refine Search"}</a>
					</div>
				{/if}
			</div>
		</div>

		{if $filterList}
			{* Data for searching within existing results *}
			<div id="keepFilters" style="display:none;">
				{foreach from=$filterList item=data key=field}
					{foreach from=$data item=value}
						<input class="existingFilter" type="checkbox" name="filter[]" value='{$value.field}:"{$value.value|escape}"'>
					{/foreach}
				{/foreach}
			</div>
		{/if}

	</form>
</div>
{/strip}