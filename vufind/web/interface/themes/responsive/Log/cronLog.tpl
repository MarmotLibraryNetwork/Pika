{strip}
	<button class="btn btn-default btn-sm" onclick="$('table.logEntryDetails a.accordion-toggle.collapsed').click()">Open All Log Entries</button>
<div class="table-responsive">
	<table class="logEntryDetails table table-hover table-condensed">
		<thead>
			<tr><th>Id</th><th>Started</th><th>Finished</th><th>Elapsed</th><th>Processes Run</th><th>Had Errors?</th><th>Notes</th></tr>
		</thead>
		<tbody>
			{foreach from=$logEntries item=logEntry}
				<tr{if $logEntry->getHadErrors()} class="danger"{/if}>
					<td><a href="#" class="accordion-toggle collapsed" id="cronEntry{$logEntry->id}" onclick="Pika.Log.toggleCronProcessInfo('{$logEntry->id}');return false;">{$logEntry->id}</a></td>
					<td>{$logEntry->startTime|date_format:"%D %T"}</td>
					<td>{$logEntry->endTime|date_format:"%D %T"}</td>
					<td>{$logEntry->getElapsedTime()}</td>
					<td>{$logEntry->getNumProcesses()}</td>
					<td>{if $logEntry->getHadErrors()}Yes{else}No{/if}</td>
					<td><a href="#" onclick="return Pika.Log.showNotes('{$logType}', '{$logEntry->id}');">Show Notes</a></td>
				</tr>
				<tr class="logEntryProcessDetails" id="processInfo{$logEntry->id}" style="display:none">
					<td colspan="7">
						<table class="logEntryProcessDetails table table-striped table-condensed">
							<thead>
								<tr><th>Process Name</th><th>Started</th><th>End Time</th><th>Elapsed</th><th>Errors</th><th>Updates</th><th>Notes</th></tr>
							</thead>
							<tbody>
							{foreach from=$logEntry->processes() item=process}
								<tr>
									<td>{$process->processName}</td>
									<td>{$process->startTime|date_format:"%D %T"}</td>
									<td>{$process->endTime|date_format:"%D %T"}</td>
									<td>{$process->getElapsedTime()}</td>
									<td>{$process->numErrors}</td>
									<td>{$process->numUpdates}</td>
									<td><a href="#" onclick="return Pika.Log.showNotes('CronProcess', '{$process->id}');">Show Notes</a></td>
								</tr>
							{/foreach}
							</tbody>
						</table>
					</td>
				</tr>
			{/foreach}
		</tbody>
	</table>
</div>
{/strip}
