<div class="related-objects results-covers home-page-browse-thumbnails">
	{foreach from=$items item=item}
		<figure class="browse-thumbnail-sorted">
			<a href="{$item.url|escape}"{if $item.name} data-title="{$item.name|escape}"{/if}>
				<img src="{if $item.thumbnail}{$item.thumbnail|escape}{else}{$defaultImage}{/if}"
				     alt="{$item.name|escape}">
			</a>
			<figcaption class="explore-more-category-title">
				<strong>{$item.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
				{if $item.relation_label} ({$item.relation_label|stripRelatorCode|escape}){/if}
			</figcaption>
		</figure>
	{/foreach}
</div>