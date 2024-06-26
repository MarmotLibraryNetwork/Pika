{strip}
	<div id="home-page-browse-header" class="row">
		<div class="col-tn-12">
			<div class="row text-center" id="browse-label">
				<h1 role="heading" aria-level="1" class="browse-label-text">Browse the Catalog</h1>
			</div>
			<div class="row text-center" id="browse-category-picker">

				<div class="jcarousel-wrapper">

					<button class="jcarousel-control-prev" aria-label="Previous Browse Category"></button>

					<div class="jcarousel" id="browse-category-carousel">
						<ul>
							{foreach from=$browseCategories item=browseCategory name="browseCategoryLoop"}
								<li id="browse-category-{$browseCategory->textId}" class="browse-category category{$smarty.foreach.browseCategoryLoop.index%9}{if (!$selectedBrowseCategory && $smarty.foreach.browseCategoryLoop.index == 0) || $selectedBrowseCategory && $selectedBrowseCategory->textId == $browseCategory->textId} selected{/if}" data-category-id="{$browseCategory->textId}">
										<button>
											{$browseCategory->label}
										</button>
								</li>
							{/foreach}
						</ul>
					</div>

					<button class="jcarousel-control-next" aria-label="Next Browse Category"></button>

					<p class="jcarousel-pagination"></p>
				</div>
				{*<div class="clearfix"></div> // Doesn't seem to be needed *}

			</div>
			<div id="browse-sub-category-menu" class="row text-center">
				{* Initial load of content done by AJAX call on page load, unless sub-category is specified via URL *}
				{if $subCategoryTextId}
					{include file="Search/browse-sub-category-menu.tpl"}
				{/if}
			</div>
		</div>
	</div>
	<div id="home-page-browse-content" class="row">
		<div class="col-tn-12">

			<div class="row" id="selected-browse-label">

				<div class="btn-group btn-group-sm" data-toggle="buttons">
					<button onclick="Pika.Browse.toggleBrowseMode(this.id)" id="covers" aria-label="change browse titles to cover layout" tabindex="0" title="Covers" class="btn btn-sm btn-default browseMode">
						<span class="thumbnail-icon"></span><span> Covers</span>
					</button>
					<button onclick="Pika.Browse.toggleBrowseMode(this.id);" id="grid" aria-label="change browse titles to grid layout" tabindex="0" title="Grid" class="btn btn-sm btn-default browseMode">
						<span class="grid-icon"></span><span> Grid</span>
					</button>
				</div>

				<div class="selected-browse-label-search">
					<a id="selected-browse-search-link" title="See the search results page for this browse category">
						<span class="icon-before"></span> {*space needed for good padding between text and icon *}
						<span class="selected-browse-label-search-text"></span>
						<span class="selected-browse-sub-category-label-search-text"></span>
						<span class="icon-after"></span>
					</a>
				</div>

			</div>

			<div id="home-page-browse-results">
				<div class="row">
				</div>
			</div>

			<button type="button" id="more-browse-results" onclick="return Pika.Browse.getMoreResults()" aria-label="Load more results for browse category">
				<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
			</button>

		</div>
	</div>
{/strip}
<script>
	$(function(){ldelim}
		{if $selectedBrowseCategory}
			Pika.Browse.curCategory = '{$selectedBrowseCategory->textId}';
			{if $subCategoryTextId}Pika.Browse.initialSubCategory = '{$subCategoryTextId}';{/if}
		{/if}
		{if !$onInternalIP}
		if (!Globals.opac && Pika.hasLocalStorage()){ldelim}
			var temp = window.localStorage.getItem('browseMode');
			if (Pika.Browse.browseModeClasses.hasOwnProperty(temp)) Pika.Browse.browseMode = temp; {* if stored value is empty or a bad value, fall back on default setting ("null" returned when not set) *}
			else Pika.Browse.browseMode = '{$browseMode}';
		{rdelim}
		else Pika.Browse.browseMode = '{$browseMode}';
		{else}
		Pika.Browse.browseMode = '{$browseMode}';
		{/if}
		$('#{$browseMode}').addClass('active'); {* show user which one is selected *}
			{if $selectedBrowseCategory && !$isDefaultCategory} {* This triggers the carousel to display the correct category (and subcategory) when these have been set with url parameters. *}
				let carousel = $("#browse-category-carousel");
				carousel.jcarousel('scroll', carousel.find("li#browse-category-" + Pika.Browse.curCategory));
			{else}
				Pika.Browse.toggleBrowseMode();
			{/if}
			Pika.Browse.setTabs();
	{rdelim});
</script>
