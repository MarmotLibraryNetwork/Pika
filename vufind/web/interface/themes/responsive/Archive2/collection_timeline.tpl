{strip}
	<div class="col-xs-12">
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}
			{include file="Archive/noAccess.tpl"}
		{else}

		{if $thumbnail}
		<div class="row">
			<div class="col-xs-12">
				<img src="{$thumbnail}" class="img-responsive thumbnail" style="max-width:300px; float:left; margin: 0 1em 1em 0;" alt="{$title|escape}">
				{$description}
				<div class="clearfix"></div>
			</div>
		</div>
		{elseif $description}
		<div class="row">
			<div class="col-xs-12">{$description}</div>
		</div>
		{/if}

		{if $recordCount}
		<p class="text-muted" style="margin-top:0.5em;">{$recordCount} objects</p>
		{/if}

		{* Year-grouped grid — grouping done in template to keep PHP simple *}
		{assign var="current_year" value=""}
		{assign var="year_open" value=false}
		{foreach from=$relatedImages item=image}
			{if $image.date != $current_year}
				{if $year_open}</div></div>{/if}
				{assign var="current_year" value=$image.date}
				{assign var="year_open" value=true}
				<div class="collection-timeline-year" style="margin-top:1.5em;">
				<h2 class="timeline-year-heading" style="border-bottom:2px solid #ccc; padding-bottom:0.25em;">{if $current_year}{$current_year}{else}Unknown{/if}</h2>
				<div class="row">
			{/if}
			<div class="col-xs-6 col-sm-4 col-md-3">
				<a href="{$image.url}" class="thumbnail">
					{if $image.thumbnail}
					<img src="{$image.thumbnail}" alt="{$image.title|escape}">
					{/if}
					<div class="caption"><p>{$image.title}</p></div>
				</a>
			</div>
		{/foreach}
		{if $year_open}</div></div>{/if}

		{if $pageLinks.all}<div class="pagination" style="margin-top:1em;">{$pageLinks.all}</div>{/if}

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
