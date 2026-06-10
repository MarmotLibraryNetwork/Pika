{* Wrapper for the filterable collection child-object display: decade date
   filters (when $showTimeline) + display-mode toggle + the object grid.
   Initial state is server-rendered; filter/place/page changes are AJAX
   reloads handled by Pika.Archive2 timeline functions. *}
<style>
	.timeline-date-filters {ldelim}
		display: flex;
		flex-wrap: wrap;
		margin-bottom: 0.75em;
	{rdelim}
	.timeline-date-filters .btn {ldelim}
		margin-bottom: 4px;
	{rdelim}
</style>
{strip}
	<div class="row">
		<div class="col-xs-12">
			{if $showTimeline}
				<div id="timeline-date-filters">
					{include file="Archive2/components/timeline_date_filters.tpl"}
				</div>
			{/if}
			{include file="Archive2/components/collection-displayMode-toggle.tpl"}
			<div id="timeline-objects">
				{include file="Archive2/components/timeline_objects_grid.tpl"}
			</div>
		</div>
	</div>
{/strip}
<script>
	$(function() {ldelim}
		Pika.Archive2.initCollectionTimeline({$nid}, {if $showTimeline}true{else}false{/if}, {if $groupByYear}true{else}false{/if});
	{rdelim});
</script>
