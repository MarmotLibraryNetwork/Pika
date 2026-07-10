{strip}
	<div class="archiveComponentContainer nopadding col-md-12 col-lg-6">
		<div class="archiveComponent browseFilterContainer">
			<div class="row archiveComponentBody">
				<div class="archiveComponentBox">
					<a href="#" onclick="return Pika.Archive.showBrowseFilterPopup('{$pid}', '{$browseFilterFacetName}', '{$browseFilterLabel}')">
						<div class="col-4 col-sm-3 col-lg-4 archiveComponentIconContainer">
							<img src="{$browseFilterImage}" width="100" height="100" alt="{$browseFilterLabel}" class="archiveComponentImage">
						</div>
						<div class="col-8 col-sm-9 col-lg-8 archiveComponentControls">
							<div class="archiveComponentHeader">{$browseFilterLabel}</div>
						</div>
					</a>
				</div>
			</div>
		</div>
	</div>
{/strip}