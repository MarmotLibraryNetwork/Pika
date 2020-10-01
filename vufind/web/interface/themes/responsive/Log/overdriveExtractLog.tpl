{strip}
	<div class="table-responsive">
		<table class="logEntryDetails table table-bordered table-hover">
			<thead>
			<tr>
				<th></th>
				<th colspan="4">Timing</th>
				<th colspan="6">Collection Updates</th>
				<th colspan="3">Title Updates</th>
				<th></th>
			</tr>
			<tr>
				<th>Id</th>
				<th>Started</th>
				<th>Last Update</th>
				<th>Finished</th>
				<th>Elapsed</th>
				<th>Num Products</th>
				<th>Num Updated</th>
				<th>Num Skipped</th>
				<th>Num Errors</th>
				<th>Num Added</th>
				<th>Num Deleted</th>
				<th>Num Titles Processed</th>
				<th>Num Availability Changes</th>
				<th>Num Metadata Changes</th>
				<th>Notes</th>
			</tr>
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
					<td>{$logEntry->numUpdated}</td>
					<td>{$logEntry->numSkipped}</td>
					<td>{$logEntry->numErrors}</td>
					<td>{$logEntry->numAdded}</td>
					<td>{$logEntry->numDeleted}</td>
					<td>{$logEntry->numTitlesProcessed}</td>
					<td>{$logEntry->numAvailabilityChanges}</td>
					<td>{$logEntry->numMetadataChanges}</td>
					<td><a href="#" onclick="return Pika.Log.showNotes('{$logType}', '{$logEntry->id}');">Show Notes</a></td>
				</tr>
      {/foreach}
			</tbody>
		</table>
	</div>
{/strip}