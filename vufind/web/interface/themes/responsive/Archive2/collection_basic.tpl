{literal}


{/literal}

{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}
			{include file="Archive/noAccess.tpl"}
		{else}

		{* Thumbnail and description *}
		<div class="row">
			{if $thumbnail}
			<div class="col-xs-4">
				{if $thumbnail_link}<a href="{$thumbnail_link}">{/if}
				<img src="{$thumbnail}" class="img-responsive" alt="{$title|escape}">
				{if $thumbnail_link}</a>{/if}
			</div>
			<div class="col-xs-8">
			{else}
			<div class="col-xs-12">
			{/if}
				{$description}
			</div>
		</div>
			
		{include file="Archive2/components/search_component.tpl"}
		{* Child objects grid *}
		{if $collectionChildren}
		<div class="row" style="margin-top: 1em;">
			<div class="col-xs-12">
				{if $recordCount}
				<p>{$recordCount} items in this collection.</p>
				{/if}
			</div>
		</div>
		
		<div class="row">
			{foreach from=$collectionChildren item=collectionChild}
			<div class="col-xs-6 col-sm-4 col-md-3">
				<a href="{$collectionChild.url}" class="thumbnail" style="border: 2px solid #ddd; border-radius: 6px; padding: 8px; background: #fff; height: 300px;">
					{if $collectionChild.thumbnail}
					<img src="{$collectionChild.thumbnail}" alt="{$collectionChild.title|escape}" style="max-height: 160px;">
					{/if}
					<div class="caption" style="padding: 9px 0;">{$collectionChild.title}</div>
				</a>
			</div>
			{/foreach}
		</div>
		{if $pageLinks.all}<div class="pagination">{$pageLinks.all}</div>{/if}
		{/if}

		{/if}
	</div>
{/strip}
{literal}
<script>
$().ready(function(){
	Pika.Archive2.loadExploreMore({/literal}{$nid}{literal});
});
</script>
{/literal}
