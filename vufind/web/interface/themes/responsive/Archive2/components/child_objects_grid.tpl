{* Child objects grid *}
{if $collectionChildren}
<div class="row">
	<div class="col-xs-12">
		{if $recordCount}
		<p>{$recordCount} items in this collection.</p>
		{/if}
		{include file="Archive2/components/collection-displayMode-toggle.tpl"}
	</div>
</div>

<div class="row collection-grid" id="collection-display-container">
	{foreach from=$collectionChildren item=collectionChild}
	{include file="Archive2/partials/collection-item.tpl"}
	{/foreach}
</div>
{if $pageLinks.all}<div class="pagination">{$pageLinks.all}</div>{/if}
{/if}
