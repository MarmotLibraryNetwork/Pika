{strip}
<div class="col-sm-6 col-md-4 col-lg-3 collection-item">
	{* Grid view: restore original card styling — fixed height, plain caption *}
	<a href="{$collectionChild.url}" class="thumbnail grid-view-item collection-item-grid-thumbnail">
		{if $collectionChild.thumbnail}
		{* alt is intentionally empty: the caption div below is inside this same <a> and
		   already renders the title as visible text, so a non-empty alt here would make
		   screen readers announce the title twice (e.g. "Jim Nimon: Jim Nimon"). Do not
		   restore alt="{$collectionChild.title}" here. *}
		<img src="{$collectionChild.thumbnail}" alt="" class="collection-item-grid-image">
		{/if}
		<div class="caption collection-item-grid-caption">{$collectionChild.title}{if $showItemDates && $collectionChild.date}<br><small class="text-muted">{$collectionChild.date}</small>{/if}</div>
	</a>
	{* List view: title above, thumbnail left, description right *}
	<div class="list-view-item">
		<h2 class="h3"><a href="{$collectionChild.url}">{$collectionChild.title}</a></h2>
		{if $showItemDates && $collectionChild.date}<p class="text-muted collection-item-list-date">{$collectionChild.date}</p>{/if}
		<div class="collection-item-list-body">
			{if $collectionChild.thumbnail}
			<a href="{$collectionChild.url}" class="collection-item-list-thumbnail">
				<img src="{$collectionChild.thumbnail}" alt="{$collectionChild.title|escape}" class="collection-item-list-image">
			</a>
			{/if}
			<div class="collection-item-list-description">{$collectionChild.description}</div>
		</div>
	</div>
</div>
{/strip}
