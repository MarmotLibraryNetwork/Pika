{strip}
<div class="table-responsive">
	<table class="logEntryDetails table table-condensed table-hover">
	<thead>
	<tr><th>Id</th><th>Started</th><th>Last Update</th><th>Finished</th><th>Elapsed</th><th>Works Processed</th><th>Lists Processed</th><th>Notes</th></tr>
	</thead>
	<tbody>
  {foreach from=$logEntries item=logEntry}
		<tr>
			<td>{$logEntry->id}</td>
			<td>{$logEntry->startTime|date_format:"%D %T"}</td>
			<td>{$logEntry->lastUpdate|date_format:"%D %T"}</td>
			<td>{$logEntry->endTime|date_format:"%D %T"}</td>
			<td>{$logEntry->getElapsedTime()}</td>
			<td>{$logEntry->numWorksProcessed}</td>
			<td>{$logEntry->numListsProcessed}</td>
			<td><a href="#" onclick="return Pika.Log.showNotes('{$logType}', '{$logEntry->id}');">Show Notes</a></td>
		</tr>
  {/foreach}
	</tbody>
</table>
</div>
{/strip}
