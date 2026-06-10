{strip}
<div class="col-xs-6 col-sm-4 col-md-3 collection-item">
	{* Grid view: restore original card styling — fixed height, plain caption *}
	<a href="{$collectionChild.url}" class="thumbnail grid-view-item" style="border: 2px solid #ddd; border-radius: 6px; padding: 8px; background: #fff; height: 300px; display: block; overflow: hidden;">
		{if $collectionChild.thumbnail}
		<img src="{$collectionChild.thumbnail}" alt="{$collectionChild.title|escape}" style="max-height: 160px; width: auto; display: block; margin: 0 auto;">
		{/if}
		<div class="caption" style="padding: 9px 0;">{$collectionChild.title}{if $showItemDates && $collectionChild.date}<br><small class="text-muted">{$collectionChild.date}</small>{/if}</div>
	</a>
	{* List view: title above, thumbnail left, description right *}
	<div class="list-view-item">
		<h2 class="h3"><a href="{$collectionChild.url}">{$collectionChild.title}</a></h2>
		{if $showItemDates && $collectionChild.date}<p class="text-muted" style="margin-top:-0.5em;">{$collectionChild.date}</p>{/if}
		<div style="display: flex; align-items: flex-start; gap: 12px;">
			{if $collectionChild.thumbnail}
			<a href="{$collectionChild.url}" style="flex-shrink: 0;">
				<img src="{$collectionChild.thumbnail}" alt="{$collectionChild.title|escape}" style="max-width: 150px; max-height: 150px; width: auto; height: auto;">
			</a>
			{/if}
			<div style="max-height: 150px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical;">{$collectionChild.description}</div>
		</div>
	</div>
</div>
{/strip}
