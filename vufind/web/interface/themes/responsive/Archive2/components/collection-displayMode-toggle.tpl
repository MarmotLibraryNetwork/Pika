<style>{*TODO: Move to _repository.scss *}
	{literal}
		.list-view-item {
			display: none;
		}

		.collection-list .list-view-item {
			display: block;
		}

		.collection-list .grid-view-item {
			display: none !important;
		}

		.collection-list .collection-item {
			width: 100%;
			float: none;
			padding-bottom: 1em;
			margin-bottom: 1em;
			border-bottom: 1px solid #ddd;
		}
	{/literal}
</style>
{strip}
	<div class="row" id="collection-displayMode-toggle">
		<div class="col-sm-12">
			<div class="btn-group btn-group-sm">
				<button tabindex="0" title="Covers" aria-label="change results to grid layout"
					onclick="Pika.Archive2.toggleCollectionDisplayMode('covers')" id="collectionModeGrid"
					class="btn btn-sm btn-outline-secondary displayMode{if $collectionDisplayMode != 'list'} active{/if}">
					<span class="thumbnail-icon"></span><span> Covers</span>
				</button>
				<button tabindex="0" title="List" aria-label="change results to list layout"
					onclick="Pika.Archive2.toggleCollectionDisplayMode('list')" id="collectionModeList"
					class="btn btn-sm btn-outline-secondary displayMode{if $collectionDisplayMode == 'list'} active{/if}">
					<span class="list-icon"></span><span> List</span>
				</button>
			</div>
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