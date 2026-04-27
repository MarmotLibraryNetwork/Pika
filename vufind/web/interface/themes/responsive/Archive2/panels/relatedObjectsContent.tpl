{strip}
<div class="related-objects-tiles-container related-objects results-covers home-page-browse-thumbnails">
	{foreach from=$relatedObjects item=obj}
		<div class="related-objects-tiles">
			<a href="{$obj.link}">
				<figure class="browse-thumbnail-sorted" style="text-align:center;">
					<img src="{$obj.image}" alt="{$obj.title|escape}" class="img-responsive">
					<figcaption class="explore-more-category-title">{$obj.title|removeTrailingPunctuation|truncate:30:"..."}</figcaption>
				</figure>
			</a>
		</div>
	{/foreach}
</div>
<div class="related-objects-search-link text-right">
	<a href="{$relatedObjectsSearchUrl}">
		{if $relatedObjectsTotal > 20}All Related Objects{else}Related Objects Search{/if}
	</a>
</div>
{/strip}
