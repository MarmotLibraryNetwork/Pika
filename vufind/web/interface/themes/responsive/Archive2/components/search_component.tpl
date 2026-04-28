{strip}
<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div class="archiveComponent">
		<div class="row archiveComponentBody">
			<div class="archiveComponentBox">
				<div class="hidden-tn hidden-xs hidden-sm col-md-4 archiveComponentIconContainer">
					<img src="{$searchComponentImage}" width="100" height="100" alt="Search" class="archiveComponentImage">
				</div>
				<div class="col-tn-12 col-md-8 archiveComponentSearchControls">
					<div id="archiveCollectionSearchLabel" class="archiveComponentHeader">Search This Collection</div>
					<form action="/Archive2/Results" id="searchComponentForm">
						<div class="input-group">
							<input aria-labelledby="archiveCollectionSearchLabel" type="text" name="lookfor" size="25" autocomplete="off" class="form-control" placeholder="">
							<div class="input-group-btn">
								<button class="btn btn-primary" type="submit">GO</button>
							</div>
							<input type="hidden" name="filter[]" value="itm_field_member_of:{$nid}">
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
{/strip}
