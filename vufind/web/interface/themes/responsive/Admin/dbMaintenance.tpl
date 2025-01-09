{strip}

	<h1 role="heading" aria-level="1" class="h2">{$pageTitleShort}</h1>

	<div id="maintenanceOptions"></div>
	<form id="dbMaintenance" action="/Admin/{$action}">
		<div>
			<table class="table">
				<thead>
					<tr>
						<th><input type="checkbox" aria-label="Select or Unselect All" id="selectAll" onclick="Pika.toggleCheckboxes('.selectedUpdate:visible', '#selectAll');" checked="checked"></th>
						<th>Release</th>
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
					{if isset($update.success)}{if $update.success} success{elseif $update.continueOnError} warning{else} danger{/if}{/if}"
					{if $update.alreadyRun && !$update.status} style="display:none"{/if}>
						<td><input aria-label="Select this database update" type="checkbox" name="selected[{$updateKey}]"{if !$update.alreadyRun} checked="checked"{/if} class="selectedUpdate"></td>
						<td>{$update.release}</td>
						<td>{$update.title}</td>
						<td>{$update.description}</td>
						<td>{if $update.alreadyRun}Yes{else}No{/if}</td>
						{if $showStatus}
						<td>{if is_array($update.status)}{foreach from=$update.status item=status}{$status}<br>{/foreach}{*smarty implode broke with php 8*}{else}{$update.status}{/if}</td>
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