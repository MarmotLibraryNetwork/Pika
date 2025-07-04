{strip}
<div class="col-xs-12">
	{if $parentExhibitUrl}
	{* Search/Archive Navigation for Exhibits within an exhibit *}
	{include file="Archive/search-results-navigation.tpl"}
	{/if}

	{if $main_image}
		<div class="main-project-image">
			<img src="{$main_image}" class="img-responsive" usemap="#map" alt='Main image for "{$title}" collection'>
		</div>
	{/if}

	<h1 role="heading" aria-level="1" class="h2">
		{$title}
		{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
	</h1>

	<div class="lead row">
		<div class="col-tn-12">
		{if $hasImageMap}
			{$imageMap}
			<script>
				$(document).ready(function(e) {ldelim}
					$('img[usemap]').addClass('img-responsive');
					$('img[usemap]').rwdImageMaps();
				{rdelim});
			</script>
		{else}
			{if $thumbnail && !$main_image}
				{if $exhibitThumbnailURL}<a href="{$exhibitThumbnailURL}">{/if}
				<img src="{$thumbnail}" class="img-responsive thumbnail exhibit-thumbnail" alt='Thumbnail for "{$title}" collection'>
				{if $exhibitThumbnailURL}</a>{/if}

			{/if}
		{/if}
		{$description}
		</div>
	</div>

	<div class="clear-both"></div>

	{if $showWidgetView}
		<div id="related-exhibit-images" class="exploreMoreBar row">
			<div class="label-top">
				<div class="exploreMoreBarLabel">{translate text='Categories'}</div>
			</div>
			<div class="exploreMoreContainer">
				<div class="jcarousel-wrapper" id="scrollExploreMore">
					{* Scrolling Buttons *}
					<button class="jcarousel-control-prev" aria-label="Previous Category"><i class="glyphicon glyphicon-chevron-left"></i></button>


					<div class="exploreMoreItemsContainer jcarousel"{* data-wrap="circular" data-jcarousel="true"*}> {* noIntialize is a filter for Pika.initCarousels() *}
						<ul>
							{foreach from=$relatedImages item=image name=loop}
								<li id="exploreMore{$smarty.foreach.loop.index}" class="explore-more-option">
									<figure class="thumbnail" title="{$image.title|escape}">
										<div class="explore-more-image">
											<a href='{$image.link}'>
												<img src="{$image.image}" alt='Thumbnail for "{$image.title|escape}"'>
											</a>
										</div>
										<figcaption class="explore-more-category-title">
											<strong>{$image.title|truncate:30}</strong>
										</figcaption>
									</figure>
								</li>
							{/foreach}
						</ul>
					</div>
					<button class="jcarousel-control-next" aria-label="Next Category"><i class="glyphicon glyphicon-chevron-right"></i></button>
				</div>
			</div>
		</div>
	{else}
		{* Standard View a la Browse Categories*}
		<div id="related-objects-for-exhibit">
			<div id="exhibit-results-loading" class="row" style="display: none">
				<div class="alert alert-info">
					Updating results, please wait.
				</div>
			</div>

			<div class="row">

			<div class="col-sm-6">
				<form action="/Archive/Results">
					<div class="input-group">
						<input type="text" name="lookfor" size="30" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="Search this collection" aria-label="Search this collection">
						<div class="input-group-btn" id="search-actions">
							<button class="btn btn-primary                " type="submit">GO</button>
						</div>
						<input type="hidden" name="islandoraType" value="IslandoraKeyword">
						<input type="hidden" name="filter[]" value='ancestors_ms:"{$pid}"'>
					</div>
				</form>
			</div>
			<div class="col-sm-5 col-sm-offset-1">
				{* Display information to sort the results (by date or by title *}
				<div class="input-group">
					<label for="results-sort" class="input-group-addon">Sort By</label>
					<select id="results-sort" name="sort" class="form-control">
						<option value="title" {if $sort=='title'}selected="selected"{/if}>Title</option>
						<option value="newest" {if $sort=='newest'}selected="selected"{/if}>Newest First</option>
						<option value="oldest" {if $sort=='oldest'}selected="selected"{/if}>Oldest First</option>
						<option value="dateAdded" {if $sort=='dateAdded'}selected="selected"{/if}>Date Added</option>
						<option value="dateModified" {if $sort=='dateModified'}selected="selected"{/if}>Date Modified</option>
					</select>
					<span class="input-group-btn">
						<button class="btn btn-primary" onclick="el=document.getElementById('results-sort');Pika.Archive.sort = el.options[el.selectedIndex ].value;Pika.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}', 1);">GO</button>
					</span>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-4">
				{if $recordCount}
					{$recordCount} objects in this collection.
				{/if}
			</div>
		</div>
		{include file="Archive/archiveCollections-displayMode-toggle.tpl"}

		{if $recordSet}
			{include file="Archive/list-list.tpl"}
		{else}
			<div id="related-exhibit-images" class="
				{if $showThumbnailsSorted && count($relatedImages) >= 18}
					row
				{elseif count($relatedImages) > 18}
					results-covers home-page-browse-thumbnails
				{elseif count($relatedImages) > 8}
					browse-thumbnails-medium
				{else}
					browse-thumbnails-few
				{/if}">
				{foreach from=$relatedImages item=image}
					{if $showThumbnailsSorted && count($relatedImages) >= 18}<div class="col-xs-6 col-sm-4 col-md-3">{/if}
						<figure class="{if $showThumbnailsSorted && count($relatedImages) >= 18}browse-thumbnail-sorted{else}browse-thumbnail{/if}">
							<a href="{$image.link}" {if $image.title}data-title="{$image.title}"{/if} onclick="return Pika.Archive.showObjectInPopup('{$image.pid|urlencode}'{if $image.recordIndex},{$image.recordIndex}{if $page},{$page}{/if}{/if})">
								<img src="{$image.image}" {if $image.title}alt="{$image.title}"{/if}>
								<figcaption class="explore-more-category-title">
									<strong>{$image.title}</strong>
								</figcaption>
							</a>
						</figure>
					{if $showThumbnailsSorted && count($relatedImages) >= 18}</div>{/if}
				{/foreach}
			</div>
		{/if}
		{* Show more link if we aren't seeing all the records already *}
		<div id="nextInsertPoint" class="text-center">
		{if $recordEnd < $recordCount}
			<button type="button" id="more-browse-results" onclick="return Pika.Archive.getMoreExhibitResults('{$pid|urlencode}')" aria-label="Load more objects">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
			</button>
		{/if}
		</div>
		</div>
	{/if}

	{if $repositoryLink && $loggedIn && $userRoles && (in_array('archives', $userRoles) || in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles))}
		<div id="more-details-accordion" class="panel-group">
			<div class="panel {*active*}{*toggle on for open*}" id="staffViewPanel">
				<a href="#staffViewPanelBody" data-toggle="collapse">
					<div class="panel-heading">
						<h2 class="panel-title">
							Staff View
						</h2>
					</div>
				</a>
				<div id="staffViewPanelBody" class="panel-collapse collapse {*in*}{*toggle on for open*}">
					<div class="panel-body">
						<a class="btn btn-small btn-default" href="{$repositoryLink}" target="_blank">
							View in Islandora
						</a>
						<a class="btn btn-small btn-default" href="{$repositoryLink}/datastream/MODS/view" target="_blank">
							View MODS Record
						</a>
						<a class="btn btn-small btn-default" href="{$repositoryLink}/datastream/MODS/edit" target="_blank">
							Edit MODS Record
						</a>
						<a class="btn btn-small btn-default" href="#" onclick="return Pika.Archive.clearCache('{$pid}');" target="_blank">
							Clear Cache
						</a>
					</div>
				</div>
			</div>
		</div>
	{/if}
</div>
{/strip}
<script>
	$(function(){ldelim}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>