{*TODO: probably obsolete. Only the Record version of this template seems to be referenced *}
<div id="page-content" class="row">
	<div id="main-content">
		<h1 role="heading" aria-level="1" class="h2">{translate text='Invalid Record'}</h1>

		<p class="alert alert-warning">Sorry, we could not find a record with an id of {$id} in our catalog. Please try your search again.</p>

		{if $enableMaterialsRequest || $externalMaterialsRequestUrl}
			{include file="MaterialsRequest/solicit-new-materials-request.tpl"}
		{/if}

	</div>
</div>