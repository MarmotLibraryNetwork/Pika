{strip}
	<div class="col-sm-12">
		{include file="Archive2/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}
			{include file="Archive/noAccess.tpl"}
		{else}

		{if $thumbnail}
		<div class="row">
			<div class="col-sm-12">
				<img src="{$thumbnail}" class="img-fluid thumbnail collection-thumbnail-float-left" alt="{$title|escape}">
				{$description}
				<div class="clearfix"></div>
			</div>
		</div>
		{elseif $description}
		<div class="row">
			<div class="col-sm-12">{$description}</div>
		</div>
		{/if}

		{* Decade date filters + year-grouped child grid (EDTF dates); filtering
		   and pagination reload via Pika.Archive2 AJAX *}
		{include file="Archive2/components/timeline_component.tpl"}

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
