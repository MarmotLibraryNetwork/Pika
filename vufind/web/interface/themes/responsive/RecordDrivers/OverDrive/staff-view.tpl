{if $recordDriver}
	<div class="row">
		<div class="result-label col-xs-3">Grouped Work ID: </div>
		<div class="col-xs-9 result-value">
			{$recordDriver->getPermanentId()}
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12">
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}" class="btn btn-sm btn-default">Go To Grouped
				Work</a>
			<button onclick="return Pika.Record.reloadCover('{$recordDriver->getModule()}', '{$id}')"
							class="btn btn-sm btn-default">Reload Cover
			</button>
			<button onclick="return Pika.GroupedWork.reloadEnrichment('{$recordDriver->getGroupedWorkId()}')"
							class="btn btn-sm btn-default">Reload Enrichment
			</button>
				{if $loggedIn}
						{if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles) || in_array('contentEditor', $userRoles))}
							<a href="/Admin/LibrarianReviews?objectAction=addNew&groupedWorkPermanentId={$recordDriver->getPermanentId()}" target="_blank" class="btn btn-sm btn-default">Add Librarian Review</a>
						{/if}
						{if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('cataloging', $userRoles))}
							<button onclick="return Pika.GroupedWork.forceReindex('{$recordDriver->getGroupedWorkId()}')" class="btn btn-sm btn-default">
								Force Reindex
							</button>
							<button onclick="return Pika.GroupedWork.forceRegrouping('{$recordDriver->getGroupedWorkId()}')" class="btn btn-sm btn-default">
								Force Regrouping
							</button>
							<button onclick="return Pika.OverDrive.forceUpdateFromAPI('{$recordDriver->getUniqueId()}')" class="btn btn-sm btn-default">
								Force Update From OverDrive API
							</button>
							<a href="/Admin/NonGroupedRecords?objectAction=addNew&recordId={$recordDriver->getId()}&source={$recordDriver->getRecordType()}&notes={$recordDriver->getTitle()|removeTrailingPunctuation|escape}%0A{$userDisplayName}, {$homeLibrary}, {$smarty.now|date_format}%0A" target="_blank" class="btn btn-sm btn-default">
								UnMerge from Work
							</a>
						{/if}
						{if $enableArchive == true && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('archives', $userRoles))}
							<button onclick="return Pika.GroupedWork.reloadIslandora('{$recordDriver->getGroupedWorkId()}')" class="btn btn-sm btn-default">
								Clear Islandora Cache
							</button>
						{/if}
				{/if}
		</div>
	</div>

	{* QR Code *}
	{include file="Record\qrcode.tpl"}

{/if}

<h3>API Extraction Dates</h3>
<div class="row">
	<div class="result-label col-xs-6">Needs Update?: </div>
	<div class="col-xs-6 result-value">
		{if $overDriveProduct->needsUpdate}Yes{else}No{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Date Added: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
		{if $overDriveProduct->dateAdded}{$overDriveProduct->dateAdded|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Date Updated: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
		{if $overDriveProduct->dateUpdated}{$overDriveProduct->dateUpdated|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	{if $overDriveProduct->deleted}
		<div class="result-label col-xs-6">Deleted: </div>
		<div class="col-xs-6 result-value">
				{* When the date is null, date_format displays the current time *}
			{if $overDriveProduct->dateDeleted}{$overDriveProduct->dateDeleted|date_format:"%b %d, %Y %T"}{/if}
		</div>
	{/if}
</div>
<div class="row">
	<div class="result-label col-xs-6">Last Metadata Check: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
		{if $overDriveProduct->lastMetadataCheck}{$overDriveProduct->lastMetadataCheck|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Last Metadata Change: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
			{if $overDriveProduct->lastMetadataChange}{$overDriveProduct->lastMetadataChange|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Last Availability Check: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
		{if $overDriveProduct->lastAvailabilityCheck}{$overDriveProduct->lastAvailabilityCheck|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Last Availability Change: </div>
	<div class="col-xs-6 result-value">
			{* When the date is null, date_format displays the current time *}
		{if $overDriveProduct->lastAvailabilityChange}{$overDriveProduct->lastAvailabilityChange|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>
<div class="row">
	<div class="result-label col-xs-6">Last Grouped Work Modification Time: </div>
	<div class="col-xs-6 result-value">
		{if $lastGroupedWorkModificationTime == 'null'}Marked for re-index{else}{$lastGroupedWorkModificationTime|date_format:"%b %d, %Y %T"}{/if}
	</div>
</div>

{if $overDriveProductRaw}
	<div id="formattedSolrRecord">
		<h3>OverDrive Product Record</h3>
		{formatJSON subject=$overDriveProductRaw}
		<h3>OverDrive MetaData</h3>
		{formatJSON subject=$overDriveMetaDataRaw}
	</div>
{/if}