<script type="text/javascript" src="/services/MaterialsRequest/ajax.js"></script>

	<div id="main-content" class="col-md-12">
		<h3>Materials Request Requests by User Report</h3>
		{if $error}
			<div class="error">{$error}</div>
		{else}
			<div id="materialsRequestFilters">
				<legend>Filters</legend>

				<form action="/MaterialsRequest/UserReport" method="get">
					<fieldset class="fieldset-collapsible">
						<legend>Statuses to Show:</legend>
						<div class="form-group checkbox">
							<label for="selectAllStatusFilter">
								<input type="checkbox" name="selectAllStatusFilter" id="selectAllStatusFilter" onclick="Pika.toggleCheckboxes('.statusFilter', '#selectAllStatusFilter');">
								<strong>Select All</strong>
							</label>
						</div>
						{foreach from=$availableStatuses item=statusLabel key=status}
							<div class="checkbox">
								<label>
									<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
								</label>
							</div>
						{/foreach}
						<div><input type="submit" name="submit" value="Update Filters" class="btn btn-default"></div>
					</fieldset>
				</form>
			</div>


			<legend>Table</legend>

			{* Display results in table*}
			<table id="summaryTable" class="table stripe table-bordered">
				<thead>
					<tr>
						<th>Last Name</th>
						<th>First Name</th>
						<th>Barcode</th>
						{foreach from=$statuses item=status}
							<th>{$status|translate}</th>
						{/foreach}
					</tr>
				</thead>
				<tbody>
					{foreach from=$userData item=userInfo key=userId}
						<tr>
							<td>{$userInfo.lastName}</td>
							<td>{$userInfo.firstName}</td>
							<td>{$userInfo.barcode}</td>
							{foreach from=$statuses key=status item=statusLabel}
								<th>{if $userInfo.requestsByStatus.$status}{$userInfo.requestsByStatus.$status}{else}0{/if}</th>
							{/foreach}
						</tr>
					{/foreach}
				</tbody>
			</table>
		{/if}

		<form action="{$fullPath}" method="get">
			<input type="submit" id="exportToExcel" name="exportToExcel" value="Export to Excel" class="btn btn-default">
			{foreach from=$availableStatuses item=statusLabel key=status}
				{if in_array($status, $statusFilter)}
					<input type="hidden" name="statusFilter[]" value="{$status}">
				{/if}
			{/foreach}
		</form>

		{* Export to Excel option *}
	</div>

<script type="text/javascript">
{literal}
	$("#startDate").datepicker();
	$("#endDate").datepicker();

{/literal}
</script>
<script type="text/javascript">
	{literal}
	$(document).ready(function(){
		$('#summaryTable').DataTable({
			"order": [[0, "asc"]],
			pageLength: 100
		});
	})

	{/literal}
</script>