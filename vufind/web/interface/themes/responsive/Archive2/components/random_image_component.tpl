{strip}
	<div class="random-image-component">
		{* aria-live announces the new title once the reload swaps this in; the placeholder
		   only ever holds one small figure, so (unlike the "load more results" status region)
		   there's no need to keep a separate sr-only announcement apart from the visible markup. *}
		<div id="randomImagePlaceholder_{$id}" aria-live="polite">
			{include file="Archive2/components/random_image_figure.tpl"}
		</div>
		<button type="button" class="btn btn-default btn-xs random-image-reload" id="randomImageReload_{$id}" onclick="return Pika.Archive2.nextRandomImage('{$id}', '{$sourceNids}');" title="Show a different random image">
			<span class="glyphicon glyphicon-refresh" aria-hidden="true"></span> New Random Image
		</button>
	</div>
	<br class="clearFix">
{/strip}
