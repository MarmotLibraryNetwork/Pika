<style>
	{literal}
	.list-view-item                    { display: none; }
	.collection-list .list-view-item   { display: block; }
	.collection-list .grid-view-item   { display: none !important; }
	.collection-list .collection-item  { width: 100%; float: none; padding-bottom: 1em; margin-bottom: 1em; border-bottom: 1px solid #ddd; }
	{/literal}
</style>
{strip}
<div class="row" id="collection-displayMode-toggle">
	<div class="btn-group btn-group-sm" data-toggle="buttons">
		<button tabindex="0" title="Grid" aria-label="change results to grid layout"
		        onclick="Pika.Archive2.toggleCollectionDisplayMode('grid')" id="collectionModeGrid"
		        class="btn btn-sm btn-default displayMode">
			<span class="thumbnail-icon"></span><span> Grid</span>
		</button>
		<button tabindex="0" title="List" aria-label="change results to list layout"
		        onclick="Pika.Archive2.toggleCollectionDisplayMode('list')" id="collectionModeList"
		        class="btn btn-sm btn-default displayMode">
			<span class="list-icon"></span><span> List</span>
		</button>
	</div>
</div>
{/strip}
{literal}
<script>
$(function() {
	Pika.Archive2.initCollectionDisplayMode();
});
</script>
{/literal}
