{strip}
{if $randomObject}
	<figure class="random-image-figure">
		<a href="{$randomObject.url}">
			{if $randomObject.thumbnail}
			<img src="{$randomObject.thumbnail}" alt="{$randomObject.title|escape}" class="img-responsive thumbnail collection-thumbnail-fit">
			{/if}
			<figcaption class="explore-more-category-title">
				<strong>{$randomObject.title|truncate:120}</strong>
			</figcaption>
		</a>
	</figure>
{/if}
{/strip}
