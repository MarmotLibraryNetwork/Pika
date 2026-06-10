{* Decade date-filter buttons for collection timeline/map displays.
   Rendered inside #timeline-date-filters and re-rendered via AJAX when the
   selected place changes (counts are place-specific). *}
{strip}
	{if $dateFacetInfo || $unknownDateCount}
		<div class="btn-group btn-group-sm timeline-date-filters" role="group" aria-label="Filter by Date">
			<button type="button" class="btn btn-default btn-sm{if !$selectedDateFilter} active{/if}" onclick="Pika.Archive2.setTimelineDateFilter('', this)">
				<strong>All</strong><br>({$recordCount})
			</button>
			{foreach from=$dateFacetInfo item=facet}
				<button type="button" class="btn btn-default btn-sm{if $selectedDateFilter == $facet.value} active{/if}" onclick="Pika.Archive2.setTimelineDateFilter('{$facet.value}', this)">
					<strong>{$facet.label}</strong><br>({$facet.count})
				</button>
			{/foreach}
			{if $unknownDateCount > 0}
				<button type="button" class="btn btn-default btn-sm{if $selectedDateFilter == 'unknown'} active{/if}" onclick="Pika.Archive2.setTimelineDateFilter('unknown', this)">
					<strong>Unknown</strong><br>({$unknownDateCount})
				</button>
			{/if}
		</div>
	{/if}
{/strip}
