{strip}
<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div id="archiveCollectionSearchLabel" class="archiveComponentHeader">Search This Collection</div>
	<form action="/Archive2/Results" id="searchComponentForm">
		<div class="input-group">
			<input aria-labelledby="archiveCollectionSearchLabel" type="text" name="lookfor" size="25"
				autocomplete="off" class="form-control" placeholder="">
			<div class="input-group-btn">
				<button class="btn btn-primary" type="submit">GO</button>
			</div>
			<input type="hidden" name="sm_title_2" value="{$title|escape url}">
			{*<input type="hidden" name="sm_collection" value="{$title|escape url}">*}
		</div>
	</form>
</div>
{/strip}