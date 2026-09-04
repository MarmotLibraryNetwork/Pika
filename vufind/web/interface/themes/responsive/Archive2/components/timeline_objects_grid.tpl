{* Filterable child-object grid for collection timeline/map displays.
   Rendered inside #timeline-objects and swapped wholesale by
   Pika.Archive2.loadTimelineObjects(). When $groupByYear is set, items are
   grouped under year headings (items arrive sorted by year, unknowns last). *}
{strip}
	{if $selectedPlaceName}
		<div class="alert alert-info timeline-place-indicator">
			{$recordCount} item{if $recordCount != 1}s{/if} for <strong>{$selectedPlaceName|escape}</strong>&nbsp;
			<a href="#" onclick="return Pika.Archive2.clearTimelinePlace();">Show all items</a>
		</div>
	{elseif $recordCount}
		<p>{$recordCount} items in this collection.</p>
	{/if}

	{if $timelineItems}
		<div class="row {$collectionDisplayModeClass}" id="collection-display-container">
			{if $groupByYear}
				{assign var="current_year" value="__none__"}
				{foreach from=$timelineItems item=collectionChild}
					{if $collectionChild.year != $current_year || $current_year == "__none__"}
						{if $current_year != "__none__"}</div></div>{/if}
						{assign var="current_year" value=$collectionChild.year}
						<div class="collection-timeline-year col-sm-12">
						<h2 class="timeline-year-heading h4">{if $collectionChild.year}{$collectionChild.year}{else}Unknown date{/if}</h2>
						<div class="row">
					{/if}
					{include file="Archive2/partials/collection-item.tpl"}
				{/foreach}
				{if $current_year != "__none__"}</div></div>{/if}
			{else}
				{foreach from=$timelineItems item=collectionChild}
					{include file="Archive2/partials/collection-item.tpl"}
				{/foreach}
			{/if}
		</div>
		{if $pageCount > 1}
			<div class="text-center">
				<ul class="pagination collection-pager">
					{if $page > 1}
						<li><a href="#" onclick="return Pika.Archive2.gotoTimelinePage(1);">&laquo; First</a></li>
						<li><a href="#" onclick="return Pika.Archive2.gotoTimelinePage({$page} - 1);">&lsaquo; Previous</a></li>
					{/if}
					<li class="disabled"><span>Page {$page} of {$pageCount}</span></li>
					{if $page < $pageCount}
						<li><a href="#" onclick="return Pika.Archive2.gotoTimelinePage({$page} + 1);">Next &rsaquo;</a></li>
						<li><a href="#" onclick="return Pika.Archive2.gotoTimelinePage({$pageCount});">Last &raquo;</a></li>
					{/if}
				</ul>
			</div>
		{/if}
	{else}
		<p>No items found{if $selectedPlaceName} for this location{/if}.</p>
	{/if}
{/strip}
