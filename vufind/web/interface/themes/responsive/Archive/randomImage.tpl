{strip}
	<a href='{$randomObject.link}'>
		<figure>
			<img src="{$randomObject.image}" alt=""{* "Alternative text of images should not be repeated as text" *}>
			<figcaption class="explore-more-category-title">
				<strong>{$randomObject.label|truncate:120}</strong>
			</figcaption>
		</figure>
	</a>
{/strip}