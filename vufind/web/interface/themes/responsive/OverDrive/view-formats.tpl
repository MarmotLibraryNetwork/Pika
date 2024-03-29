{strip}
{if !empty($overDriveTitleHoldings)}
	{foreach from=$overDriveTitleHoldings item=overDriveFormat key=index}
	<div id="itemRow{$overDriveFormat->id}" class="eContentHolding">
		<div class="eContentHoldingHeader">
			<div class="row">
				<div class="col-sm-9">
					<span class="eContentHoldingFormat">{$overDriveFormat->name}</span>
					{if $showEContentNotes}
						{$overDriveFormat->notes}
					{/if}
				</div>
			</div>

			<div class="row eContentHoldingUsage">
				<div class="col-sm-12">
					{$overDriveFormat->getFormatNotes()}
				</div>
			</div>
		</div>

		<div class="eContentHoldingNotes">
				{if $overDriveFormat->size != 0 && strcasecmp($overDriveFormat->size, 'unknown') != 0}
				Size: {$overDriveFormat->fileSize|file_size}<br>
				{/if}
		</div>
		<div class="eContentHoldingActions">
			{if $overDriveFormat->sampleUrl_1}
				<a href="{$overDriveFormat->sampleUrl_1}" class="btn btn-sm btn-default">{translate text="Sample"}{if $overDriveFormat->sampleName_1}: {$overDriveFormat->sampleName_1}{/if}</a>
				&nbsp;
			{/if}
			{if $overDriveFormat->sampleUrl_2}
				<a href="{$overDriveFormat->sampleUrl_2}" class="btn btn-sm btn-default">{translate text="Sample"}{if $overDriveFormat->sampleName_2}: {$overDriveFormat->sampleName_2}{/if}</a>
				&nbsp;
			{/if}
			{* Options for the user to view online or download *}
		</div>
	</div>
	{/foreach}
	<div id="formatHelp">
		Need Help?<br>
		{include file='OverDrive/overdrive-help.tpl'}
	</div>
{else}
	No Copies Found
{/if}

{/strip}