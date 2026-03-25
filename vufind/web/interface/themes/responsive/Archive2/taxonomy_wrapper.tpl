{strip}
{include file="Archive/search-results-navigation.tpl"}
<h1 role="heading" aria-level="1" class="h2">{$term_title}</h1>

<div class="col-xs-6">
	{if $thumbnail && $thumbnail.url}
	<img src="{$thumbnail.url|escape}"
	     alt="{$term_title|escape}"
	     class="img-responsive taxonomy-thumbnail "
	     style="max-width:390px; margin:0; float: left;">
	{/if}
</div>
<div class="col-xs-6">

	{if $term_description}
		<div class="taxonomy-description well">
			{$term_description}
		</div>
	{/if}
	{if $address}
		<div class="taxonomy-address">
		<div class="result-label">Address</div>
			{if $address.street}
				{$address.street}<br />
			{/if}
			{if $address.city}
				{$address.city}<br />
			{/if}
			{if $address.state}
				{$address.state}<br />
			{/if}
		</div>
	{/if}
</div>
	<br class="clearfix" style="clear: both">
	{include file="Archive2/$taxonomy_type_template.tpl"}

	{include file="Archive2/taxonomy_related_objects.tpl"}

	{include file="Archive2/taxonomy_metadata.tpl"}
</div>
{/strip}
