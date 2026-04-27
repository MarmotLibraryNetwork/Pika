{strip}
<div class="col-xs-12">
	{include file="Archive/search-results-navigation.tpl"}
	<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

	{if $can_view == false}
		{include file="Archive/noAccess.tpl"}
	{else}

	<div class="row">
		<div class="col-xs-12">
			{if $thumbnail}
			<img src="{$thumbnail}" class="img-responsive thumbnail" style="max-width:300px; float:left; margin: 0 1em 1em 0;" alt="{$title|escape}">
			{/if}
			{$description}
			<div class="clearfix"></div>
		</div>
	</div>

	<div class="row">
		{foreach from=$collectionTemplates item=template}
			{$template}
		{/foreach}
	</div>

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
