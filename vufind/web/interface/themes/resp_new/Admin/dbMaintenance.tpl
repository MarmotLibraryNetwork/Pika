{strip}
	{if $action=='DBMaintenanceEContent'}
		<h1>Database Maintenance eContent</h1>
	{else}
		<h1>Database Maintenance</h1>
	{/if}
	<div id="maintenanceOptions"></div>
	<form id="dbMaintenance" action="/Admin/{$action}">
		<div>
			<table class="table">
				<thead>
					<tr>
						<th><input type="checkbox" id="selectAll" onclick="Pika.toggleCheckboxes('.selectedUpdate:visible', '#selectAll');" checked="checked"></th>
						<th>Name</th>
						<th>Description</th>
						<th>Already Run?</th>
						{if $showStatus}
						<th>Status</th>
						{/if}
					</tr>
				</thead>
				<tbody>
					{foreach from=$sqlUpdates item=update key=updateKey}
					<tr class="{if $update.alreadyRun}updateRun{else}updateNotRun{/if}
					{if $update.status == 'Update succeeded'} success{elseif strpos($update.status, 'Warning') !== false} warning{elseif strpos($update.status, 'fail') !== false || strpos($update.status, 'error') !== false} danger{/if}"
					{if $update.alreadyRun && !$update.status} style="display:none"{/if}>
						<td><input type="checkbox" name="selected[{$updateKey}]"{if !$update.alreadyRun} checked="checked"{/if} class="selectedUpdate"></td>
						<td>{$update.title}</td>
						<td>{$update.description}</td>
						<td>{if $update.alreadyRun}Yes{else}No{/if}</td>
						{if $showStatus}
						<td>{$update.status}</td>
						{/if}
					</tr>
					{/foreach}
				</tbody>
			</table>
			<div class="form-inline">
				<div class="form-group">
					<input type="submit" name="submit" class="btn btn-primary" value="Run Selected Updates">
				</div>
				<div class="form-group checkbox checkbox-inline">
					&nbsp; &nbsp;
					<label for="hideUpdatesThatWereRun">
						<input type="checkbox" name="hideUpdatesThatWereRun" id="hideUpdatesThatWereRun" checked="checked"
						       onclick="$('.updateRun').toggle();"> Hide updates that have been run
					</label>
				</div>
			</div>
		</div>
	</form>
{/strip}