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

	{*
		Custom collection layout. Control arrangement here.

		Which components appear is driven by the collection's field_pika_coll_options
		config (handled in Collection::loadCustomComponents): singletons arrive as
		boolean flags, repeatable components as arrays. Each instance's data is passed
		into the component via {include ... var=$value} so the same component can be
		reused without variable collisions. Rearrange / regroup the blocks below to
		change the layout; group components of the same width in a row to avoid gaps.
	*}
	{if $scrollerComponents}
		<div class="row">
			{foreach from=$scrollerComponents item=scroller}
				{include file="Archive2/components/scroller_component.tpl"
					browseCollectionTitle=$scroller.title
					browseCollectionItems=$scroller.items
					browseCollectionNid=$scroller.nid}
			{/foreach}
		</div>
	{/if}

	{if $showMapComponent}
		<div class="row">
			{include file="Archive2/components/map_component.tpl"}
			{include file="Archive2/components/timeline_component.tpl"}
		</div>
	{/if}

	{if $showSearchComponent}
		<div class="row">
			{include file="Archive2/components/search_component.tpl"}
		</div>
	{/if}

	{if $browseFilterComponents}
		<div class="row">
			{foreach from=$browseFilterComponents item=filter}
				{if $filter.entity}
					{include file="Archive2/components/entity_filter_component.tpl"
						browseFilterFacetName=$filter.facet
						browseFilterLabel=$filter.label
						browseFilterImage=$filter.image}
				{else}
					{include file="Archive2/components/browse_filter_component.tpl"
						browseFilterFacetName=$filter.facet
						browseFilterLabel=$filter.label
						browseFilterImage=$filter.image}
				{/if}
			{/foreach}
		</div>
	{/if}

	{if $browseTitleComponents || $randomImageComponents}
		<div class="row">
			{foreach from=$browseTitleComponents item=browseTitle}
				{include file="Archive2/components/browse_titles_component.tpl"
					browseCollectionTitle=$browseTitle.title
					browseCollectionItems=$browseTitle.items}
			{/foreach}
			{foreach from=$randomImageComponents item=random}
				{include file="Archive2/components/random_image_component.tpl"
					randomObject=$random.object}
			{/foreach}
		</div>
	{/if}

	{if $browseByComponents}
		<div class="row">
			{foreach from=$browseByComponents item=browseBy}
				{include file="Archive2/components/browse_by_component.tpl"
					browseByTitle=$browseBy.title
					browseByColumn1=$browseBy.col1
					browseByColumn2=$browseBy.col2}
			{/foreach}
		</div>
	{/if}

	{if $showBrowseAll}
		<div class="row">
			{include file="Archive2/components/browse_all_component.tpl"}
		</div>
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
