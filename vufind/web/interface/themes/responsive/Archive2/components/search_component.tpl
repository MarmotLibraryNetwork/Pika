{strip}
<div class="archiveComponentContainer col-sm-12 col-md-6">
<hr>
	<form action="/Archive2/Results" id="searchComponentForm">
		<div class="input-group">
			<input aria-labelledby="archiveCollectionSearchLabel" type="text" name="lookfor" size="25"
				autocomplete="off" class="form-control" placeholder="Search this Collection" aria-label="Search this collection">
			<div class="input-group-btn">
				<button class="btn btn-primary" type="submit">GO</button>
			</div>
			<input type="hidden" name="sm_collection" value="{$title|escape:'url'}">
		</div>
	</form>
</div>
{/strip}