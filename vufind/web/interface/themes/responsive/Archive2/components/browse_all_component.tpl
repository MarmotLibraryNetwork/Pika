{strip}
<div class="nopadding col-sm-12">
	<div class="exploreMoreBar row">
		<div class="label-top">
			<div class="exploreMoreBarLabel">
				<div class="archiveComponentHeader">Browse All</div>
			</div>
		</div>
	</div>

	{if $collectionChildren}
		<div class="row collection-row-spacer">
			<div class="col-xs-12">
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
</div>
{/strip}
