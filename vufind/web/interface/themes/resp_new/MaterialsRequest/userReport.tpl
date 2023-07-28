	<div id="main-content" class="col-md-12">
		<h3>Materials Request Requests by User Report</h3>
		{if $error}
			<div class="alert alert-danger">{$error}</div>
		{/if}
			<div id="materialsRequestFilters">
				<legend>Filters</legend>

				<form action="/MaterialsRequest/UserReport" method="get">
					<fieldset class="fieldset-collapsible">
{*					<fieldset class="fieldset-collapsible{if !empty($statusFilter)} fieldset-init-open{/if}">*}
						<legend>Statuses to Show:</legend>
						<div class="form-group checkbox">
							<label for="selectAllStatusFilter">
								<input type="checkbox" name="selectAllStatusFilter" id="selectAllStatusFilter" onclick="Pika.toggleCheckboxes('.statusFilter', '#selectAllStatusFilter');">
								<strong>Select All</strong>
							</label>
						</div>
						<div class="form-group"><strong>Default Status</strong>
								{foreach from=$defaultStatuses item=statusLabel key=status}
									<div class="checkbox">
										<label>
											<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
										</label>
									</div>
								{/foreach}
						</div>
						<div class="form-group"><strong>Open Statuses</strong>
								{foreach from=$openStatuses item=statusLabel key=status}
									<div class="checkbox">
										<label>
											<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
										</label>
									</div>
								{/foreach}
						</div>
						<div class="form-group"><strong>Closed Statuses</strong>
								{foreach from=$closedStatuses item=statusLabel key=status}
									<div class="checkbox">
										<label>
											<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
										</label>
									</div>
								{/foreach}
						</div>
					</fieldset>
					<fieldset class="form-group fieldset-collapsible{if ($startDate || $endDate)} fieldset-init-open{/if}">
						<legend>Date:</legend>
						<div class="form-group">
							<label for="startDate" class="control-label col-sm-2">Start Date</label>
							<div class="input-group input-append date controls col-sm-3" id="startDatePicker">
								<input type="text" name="startDate" id="startDate" size="10" value="{$startDate|date_format:'%m/%d/%Y'}"
											 data-provide="datepicker" data-date-format="mm/dd/yyyy" data-date-end-date="0d"
											 class="form-control" >
								<span class="input-group-addon">
											<span class="glyphicon glyphicon-calendar"
														onclick="$('#startDate').focus().datepicker('show')"
														aria-hidden="true">
											</span>
										</span>
							</div>
						</div>
						<div class="form-group">
							<label for="endDate" class="control-label col-sm-2">End Date</label>
							<div class="input-group input-append date controls col-sm-3" id="endDatePicker">
								<input type="text" name="endDate" id="endDate" size="10" value="{$endDate|date_format:'%m/%d/%Y'}"
											 data-provide="datepicker" data-date-format="mm/dd/yyyy" data-date-end-date="0d"
											 class="form-control">
								<span class="input-group-addon">
											<span class="glyphicon glyphicon-calendar"
														onclick="$('#endDate').focus().datepicker('show')"
														aria-hidden="true">
											</span>
										</span>
							</div>
						</div>
					</fieldset>
					<div><input type="submit" name="submit" value="Update Filters" class="btn btn-default"></div>
				</form>
			</div>

		{if !empty($statuses)}
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

			{* Export to Excel option *}
		<form action="{$fullPath}" method="get">
			<input type="submit" id="exportToExcel" name="exportToExcel" value="Export to Excel" class="btn btn-default">
				{foreach from=$statusFilter item=status}
					<input type="hidden" name="statusFilter[]" value="{$status}">
				{/foreach}
		</form>

		{/if}

	</div>

<script type="text/javascript">
{literal}
	$("#startDate").datepicker();
	$("#endDate").datepicker();

	$(document).ready(function(){
		$('#summaryTable').DataTable({
			"order": [[0, "asc"]],
			pageLength: 100
		});
	})

	{/literal}
</script>