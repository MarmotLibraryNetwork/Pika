{strip}
	<div class="random-image-component" style="height: 275px;">
		<div id="randomImagePlaceholder_{$id}">
			{include file="Archive2/components/random_image_figure.tpl"}
		</div>
		<button type="button" class="btn btn-default btn-xs random-image-reload" onclick="return Pika.Archive2.nextRandomImage('{$id}', '{$sourceNids}');" title="Show a different random image">
			<span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> New Random Image
		</button>
	</div>
	<br class="clearFix">
{/strip}
