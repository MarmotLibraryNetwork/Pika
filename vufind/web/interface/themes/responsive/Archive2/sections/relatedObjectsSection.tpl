{strip}
	{if $related_objects}
		<div class="related-objects results-covers home-page-browse-thumbnails">
			{foreach from=$related_objects item=obj}
				<figure class="browse-thumbnail-sorted">
					<a href="/Archive2/Node/{$obj.nid}"{if $obj.title} data-title="{$obj.title|escape}"{/if}>
						<img src="{if $obj.thumbnail}{$obj.thumbnail|escape}{else}/interface/themes/responsive/images/archive_placeholder_1.png{/if}"
						     alt="{$obj.title|escape}">
					</a>
					<figcaption class="explore-more-category-title">
						<strong>{$obj.title|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
						{if $obj.note}<br>{$obj.note|escape}{/if}
					</figcaption>
				</figure>
			{/foreach}
		</div>
	{/if}
{/strip}
