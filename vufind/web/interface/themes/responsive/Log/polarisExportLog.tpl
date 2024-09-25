{strip}
	<div class="table-responsive">
		<table class="logEntryDetails table table-condensed table-hover">
			<thead>
			<tr><th>Id</th><th>Started</th><th>Last Update</th><th>Finished</th><th>Elapsed</th>
				<th>Records Added</th>
				<th>Records Updated</th>
				<th>Records Deleted</th>
				<th>Errors</th>
				<th>Items Updated/Added</th>
				<th>Items Deleted</th>
				<th>Marked To Process</th><th>Processed</th>
				<th>Notes</th></tr>
			</thead>
			<tbody>
			{foreach from=$logEntries item=logEntry}
				<tr>
					<td>{$logEntry->id}</td>
					<td>{$logEntry->startTime|date_format:"%D %T"}</td>
					<td>{$logEntry->lastUpdate|date_format:"%D %T"}</td>
					<td>{$logEntry->endTime|date_format:"%D %T"}</td>
					<td>{$logEntry->getElapsedTime()}</td>
					<td>{$logEntry->numRecordsAdded}</td>
					<td>{$logEntry->numRecordsUpdated}</td>
					<td>{$logEntry->numRecordsDeleted}</td>
					<td>{$logEntry->numErrors}</td>
					<td>{$logEntry->numItemsUpdated}</td>
					<td>{$logEntry->numItemsDeleted}</td>
					<td>{$logEntry->numRecordsToProcess}</td>
					<td>{$logEntry->numRecordsProcessed}</td>
					<td><a class="btn btn-link" onclick="return Pika.Log.showNotes('{$logType}', '{$logEntry->id}');">Show Notes</a></td>
				</tr>
			{/foreach}
			</tbody>
		</table>
	</div>
{/strip}