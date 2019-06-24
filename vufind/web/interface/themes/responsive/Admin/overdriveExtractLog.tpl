{strip}
	<div id="main-content" class="col-md-12">
		<h3>OverDrive Extract Log</h3>

		{if $numOutstandingChanges > 0}
			<div class="alert {if $numOutstandingChanges > 500}alert-danger{else}alert-warning{/if}">
				There are {$numOutstandingChanges} changes that still need to be loaded from the API.
			</div>
		{/if}
	<form class="navbar form-inline row">
		<div class="form-group col-xs-7">
			<label for="productsLimit" class="control-label">Min Products Processed: </label>
			<input style="width: 125px;" id="productsLimit" name="productsLimit" type="number" min="0" class="form-control" {if !empty($smarty.request.productsLimit)} value="{$smarty.request.productsLimit}"{/if}>
			<button class="btn btn-primary" type="submit">Go</button>
		</div>
		<div class="form-group col-xs-5">
				<span class="pull-right">
					<label for="pagesize" class="control-label">Entries Per Page&nbsp;</label>
					<select id="pagesize" name="pagesize" class="pagesize form-control input-sm" onchange="VuFind.changePageSize()">
						<option value="30"{if $recordsPerPage == 30} selected="selected"{/if}>30</option>
						<option value="50"{if $recordsPerPage == 50} selected="selected"{/if}>50</option>
						<option value="75"{if $recordsPerPage == 75} selected="selected"{/if}>75</option>
						<option value="100"{if $recordsPerPage == 100} selected="selected"{/if}>100</option>
					</select>
				</span>
		</div>
	</form>
	<div id="econtentAttachLogContainer">

		<div>
			<table class="logEntryDetails table table-bordered table-striped">
				<thead>
					<tr><th>Id</th><th>Started</th><th>Last Update</th><th>Finished</th><th>Elapsed</th><th>Num Products</th><th>Num Errors</th><th>Num Added</th><th>Num Deleted</th><th>Num Updated</th><th>Num Skipped</th><th>Num Availability Changes</th><th>Num Metadata Changes</th><th>Notes</th></tr>
				</thead>
				<tbody>
					{foreach from=$logEntries item=logEntry}
						<tr>
							<td>{$logEntry->id}</td>
							<td>{$logEntry->startTime|date_format:"%D %T"}</td>
							<td>{$logEntry->lastUpdate|date_format:"%D %T"}</td>
							<td>{$logEntry->endTime|date_format:"%D %T"}</td>
							<td>{$logEntry->getElapsedTime()}</td>
							<td>{$logEntry->numProducts}</td>
							<td>{$logEntry->numErrors}</td>
							<td>{$logEntry->numAdded}</td>
							<td>{$logEntry->numDeleted}</td>
							<td>{$logEntry->numUpdated}</td>
							<td>{$logEntry->numSkipped}</td>
							<td>{$logEntry->numAvailabilityChanges}</td>
							<td>{$logEntry->numMetadataChanges}</td>
							<td><a href="#" onclick="return VuFind.Admin.showOverDriveExtractNotes('{$logEntry->id}');">Show Notes</a></td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>

		{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
	</div>
{/strip}