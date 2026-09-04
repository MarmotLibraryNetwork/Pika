{* Child objects grid *}
{if $collectionChildren}
<div class="row">
	<div class="col-sm-12">
		{if $recordCount}
		<p>{$recordCount} items in this collection.</p>
		{/if}
		{include file="Archive2/components/collection-displayMode-toggle.tpl"}
	</div>
</div>

<div class="row {$collectionDisplayModeClass}" id="collection-display-container">
	{foreach from=$collectionChildren item=collectionChild}
	{include file="Archive2/partials/collection-item.tpl"}
	{/foreach}
</div>
{include file="Archive2/components/collection-pager.tpl"}
{/if}
