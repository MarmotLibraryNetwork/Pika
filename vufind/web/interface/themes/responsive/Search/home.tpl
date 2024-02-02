{strip}
	<div id="home-page-browse-header" class="row">
		<div class="col-sm-12">
			<div class="row text-center" id="browse-label">
				<span class="browse-label-text">BROWSE THE CATALOG</span>
			</div>
			<div class="row text-center" id="browse-category-picker">
				<div class="jcarousel-wrapper">
					<div class="jcarousel" id="browse-category-carousel">
						<ul>
							{foreach from=$browseCategories item=browseCategory name="browseCategoryLoop"}
								<li id="browse-category-{$browseCategory->textId}" class="browse-category category{$smarty.foreach.browseCategoryLoop.index%9}{if (!$selectedBrowseCategory && $smarty.foreach.browseCategoryLoop.index == 0) || $selectedBrowseCategory && $selectedBrowseCategory->textId == $browseCategory->textId} selected{/if}" data-category-id="{$browseCategory->textId}">
										<div >
											{$browseCategory->label}
										</div>
								</li>
							{/foreach}
						</ul>
					</div>

					<a href="#" class="jcarousel-control-prev"></a>
					<a href="#" class="jcarousel-control-next"></a>

					<p class="jcarousel-pagination"></p>
				</div>
				<div class="clearfix"></div>
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
		<div class="col-sm-12">

			<div class="row" id="selected-browse-label">

				<div class="btn-group btn-group-sm" data-toggle="buttons">
					<label for="covers" title="Covers" class="btn btn-sm btn-default"><input onchange="Pika.Browse.toggleBrowseMode(this.id)" type="radio" id="covers">
						<span class="thumbnail-icon"></span><span> Covers</span>
					</label>
					<label for="grid" title="Grid" class="btn btn-sm btn-default"><input onchange="Pika.Browse.toggleBrowseMode(this.id);" type="radio" id="grid">
						<span class="grid-icon"></span><span> Grid</span>
					</label>
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

			<a onclick="return Pika.Browse.getMoreResults()" role="button">
				<div class="row" id="more-browse-results">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</div>
			</a>
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
		$('#'+Pika.Browse.browseMode).parent('label').addClass('active'); {* show user which one is selected *}
			{if $selectedBrowseCategory && !$isDefaultCategory} {* This triggers the carousel to display the correct category (and subcategory) when these have been set with url parameters. *}
				let carousel = $("#browse-category-carousel");
				carousel.jcarousel('scroll', carousel.find("li#browse-category-" + Pika.Browse.curCategory));
			{else}
				Pika.Browse.toggleBrowseMode();
			{/if}
	{rdelim});
</script>
