<style>
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

		div.btn-group span.thumbnail-icon {
			position: relative;
			top: 1px;
			display: inline-block;
			font-family: 'Glyphicons Halflings';
			font-style: normal;
			font-weight: normal;
			line-height: 1;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
		}

		div.btn-group span.thumbnail-icon:empty {
			width: 1em;
		}

		div.btn-group span.thumbnail-icon:before {
			content: "\e011";
		}
		div.btn-group span.list-icon {
			position: relative;
			top: 1px;
			display: inline-block;
			font-family: 'Glyphicons Halflings';
			font-style: normal;
			font-weight: normal;
			line-height: 1;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
		}

		div.btn-group span.list-icon:empty {
			width: 1em;
		}

		div.btn-group span.list-icon:before {
			content: "\e012";
		}


	{/literal}
</style>
{strip}
	<div class="row" id="collection-displayMode-toggle">
		<div class="col-xs-12">
			<div class="btn-group btn-group-sm" data-toggle="buttons">
				<button tabindex="0" title="Covers" aria-label="change results to grid layout"
					onclick="Pika.Archive2.toggleCollectionDisplayMode('grid')" id="collectionModeGrid"
					class="btn btn-sm btn-default displayMode">
					<span class="thumbnail-icon"></span><span> Covers</span>
				</button>
				<button tabindex="0" title="List" aria-label="change results to list layout"
					onclick="Pika.Archive2.toggleCollectionDisplayMode('list')" id="collectionModeList"
					class="btn btn-sm btn-default displayMode">
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