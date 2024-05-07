{strip}
	<div class="row">
		<div class="resultDetails col-xs-12 col-md-9">
        {*		<span class="result-index">{$resultIndex}.</span>&nbsp;*}
			<span class="result-title">
            {translate text='Supplemental Material'}
			</span>

			{if !empty($supplementalTitle.formats) || ($supplementalTitle.isFormatSelected && isset($supplementalTitle.selectedFormat) && $supplementalTitle.selectedFormat.formatType != 'video-streaming' && $supplementalTitle.selectedFormat.formatType != 'magazine-overdrive')}
				<div class="row econtent-download-row">
					<div class="result-label col-md-4 col-lg-3">{translate text='Download'}</div>
					<div class="result-value col-md-8 col-lg-9">
							{if $supplementalTitle.isFormatSelected && isset($supplementalTitle.selectedFormat)}
								The <strong>{$supplementalTitle.selectedFormat.name}</strong> format is available.
							{elseif !empty($supplementalTitle.formats)}
								<div class="form-inline">
									<label for="downloadFormat_{$supplementalTitle.overDriveId}">Select one format to download.</label>
									<br>
									<select name="downloadFormat_{$supplementalTitle.overDriveId}" id="downloadFormat_{$supplementalTitle.overDriveId}" class="input-sm form-control">
										<option value="-1">Select a Format</option>
											{foreach from=$supplementalTitle.formats item=format}
												<option value="{$format.formatType}">{$format.name}</option>
											{/foreach}
									</select>
									<button onclick="Pika.OverDrive.selectOverDriveDownloadFormat('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}')" class="btn btn-sm btn-primary">Download</button>
								</div>
							{/if}
					</div>
				</div>
			{/if}

		</div>

        {* Actions for Title *}
			<div class="col-xs-9 col-sm-8 col-md-4 col-lg-3">
				<div class="btn-group btn-group-vertical btn-block">
					<button onclick="return Pika.OverDrive.followOverDriveDownloadLink('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}')" class="btn btn-sm btn-primary">Get {if $supplementalTitle.mediaType}{$supplementalTitle.mediaType}{else}eContent{/if}</button>
{*  The API reports an early return action but it doesn't actually work
            {if $supplementalTitle.earlyReturn}
							<a href="#" onclick="return Pika.OverDrive.returnOverDriveTitle('{$supplementalTitle.userId}', '{$supplementalTitle.overDriveId}', '{$supplementalTitle.transactionId}');" class="btn btn-sm btn-warning">Return&nbsp;Now</a>
            {/if}
*}
				</div>


			</div>

	</div>
{/strip}