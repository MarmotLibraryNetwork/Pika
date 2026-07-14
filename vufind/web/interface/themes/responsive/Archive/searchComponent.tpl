{strip}
	<div class="archiveComponentContainer nopadding col-md-12 col-lg-6">
		<div class="archiveComponent">
			<div class="row archiveComponentBody">
				<div class="archiveComponentBox">
					<div class="d-none d-lg-block col-lg-4 archiveComponentIconContainer">
					<img src="{$searchComponentImage}" width="100" height="100" alt="Search" class="archiveComponentImage">
					</div>
					<div class="col-12 col-lg-8 archiveComponentSearchControls">
						<div id="archiveCollectionSearchLabel" class="archiveComponentHeader">Search This Collection</div>
						<form action="/Archive/Results" id="searchComponentForm">
							<div class="input-group">
								<input aria-labelledby="archiveCollectionSearchLabel" type="text" name="lookfor" size="25" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="">
								<button class="btn btn-primary" type="submit" id="search-actions">GO</button>
								<input type="hidden" name="islandoraType" value="IslandoraKeyword">
								<input type="hidden" name="filter[]" value='ancestors_ms:"{$pid}"'>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
{/strip}