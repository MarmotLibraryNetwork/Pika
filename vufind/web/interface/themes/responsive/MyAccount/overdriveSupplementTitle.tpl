{strip}
	<div class="row">
		<div class="resultDetails col-xs-12 col-md-9">
        {*		<span class="result-index">{$resultIndex})</span>&nbsp;*}
			<span class="result-title notranslate">
            {translate text='Supplemental Material'}
				</span>

			<div class="row econtent-download-row">
				<div class="result-label col-md-4 col-lg-3">{translate text='Download'}</div>
				<div class="result-value col-md-8 col-lg-9">
            {if $supplementalTitle.formatSelected}
							The <strong>{$supplementalTitle.selectedFormat.name}</strong> format is available.
            {elseif isset($supplementalTitle.formats)}
							<div class="form-inline">
								<label for="downloadFormat_{$supplementalTitle.overDriveId}">Select one format to download.</label>
								<br>
								<select name="downloadFormat_{$supplementalTitle.overDriveId}" id="downloadFormat_{$supplementalTitle.overDriveId}" class="input-sm form-control">
									<option value="-1">Select a Format</option>
                    {foreach from=$supplementalTitle.formats item=format}
											<option value="{$format.id}">{$format.name}</option>
                    {/foreach}
								</select>
								<a href="#" onclick="VuFind.OverDrive.selectOverDriveDownloadFormat('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}')" class="btn btn-sm btn-primary">Download</a>
							</div>
            {/if}
				</div>
			</div>

		</div>

        {* Actions for Title *}
			<div class="col-xs-9 col-sm-8 col-md-4 col-lg-3">
				<div class="btn-group btn-group-vertical btn-block">
            {if $supplementalTitle.overdriveMagazine}
							<a href="#" onclick="return VuFind.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', 'magazine-overdrive')" class="btn btn-sm btn-primary">Read&nbsp;Online</a>
            {/if}
            {if $supplementalTitle.overdriveRead}
							<a href="#" onclick="return VuFind.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', 'ebook-overdrive')" class="btn btn-sm btn-primary">Read&nbsp;Online</a>
            {/if}
            {if $supplementalTitle.overdriveListen}
							<a href="#" onclick="return VuFind.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', 'audiobook-overdrive')" class="btn btn-sm btn-primary">Listen&nbsp;Online</a>
            {/if}
            {if $supplementalTitle.overdriveVideo}
							<a href="#" onclick="return VuFind.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', 'video-streaming')" class="btn btn-sm btn-primary">Watch&nbsp;Online</a>
            {/if}
            {if $supplementalTitle.formatSelected && !$supplementalTitle.overdriveVideo}
							<a href="#" onclick="return VuFind.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', '{$supplementalTitle.selectedFormat.format}')" class="btn btn-sm btn-primary">Download</a>
            {/if}
            {if $supplementalTitle.earlyReturn}
							<a href="#" onclick="return VuFind.OverDrive.returnOverDriveTitle('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', '{$supplementalTitle.transactionId}');" class="btn btn-sm btn-warning">Return&nbsp;Now</a>
            {/if}
				</div>


			</div>

	</div>
{/strip}