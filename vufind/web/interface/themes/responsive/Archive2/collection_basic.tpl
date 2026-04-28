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

		{* Child objects grid *}
		{if $relatedImages}
		<div class="row" style="margin-top: 1em;">
			<div class="col-xs-12">
				{if $recordCount}
				<p class="text-muted">{$recordCount} objects</p>
				{/if}
			</div>
		</div>
		<div class="row">
			{foreach from=$relatedImages item=image}
			<div class="col-xs-6 col-sm-4 col-md-3">
				<a href="{$image.url}" class="thumbnail" style="border: 2px solid #ddd; border-radius: 6px; padding: 8px; background: #fff; height: 300px;">
					{if $image.thumbnail}
					<img src="{$image.thumbnail}" alt="{$image.title|escape}" style="max-height: 160px;">
					{/if}
					<div class="caption" style="padding: 9px 0;">{$image.title}</div>
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
	//Pika.Archive2.loadExploreMore({/literal}{$nid}{literal});
});
</script>
{/literal}
