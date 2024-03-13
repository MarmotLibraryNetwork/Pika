{strip}
	<h1 role="heading" class="h2">{translate text='Invalid Record'}</h1>
	<p class="alert alert-warning">Sorry, we could not find a record with an id of <strong>{$id}</strong> in our catalog.
		Please try your search again.</p>
		{if $enableMaterialsRequest || $externalMaterialsRequestUrl}
				{include file="MaterialsRequest/solicit-new-materials-request.tpl"}
		{/if}
{/strip}