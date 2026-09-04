	<div id="main-content" class="col-lg-12">
		<h1 role="heading" aria-level="1" class="h2">Materials Request Summary Report</h1>
		{if $error}
			<div class="alert alert-warning">{$error}</div>
		{else}


<legend>Filters</legend>

						<form action="/MaterialsRequest/SummaryReport" method="get">
							<fieldset class="fieldset-collapsible form-horizontal">
								<legend>Statuses to Show:</legend>
								<div class="mb-3 checkbox">
									<label for="selectAllStatusFilter">
										<input type="checkbox" name="selectAllStatusFilter" id="selectAllStatusFilter" onclick="Pika.toggleCheckboxes('.statusFilter', '#selectAllStatusFilter');">
										<strong>Select All</strong>
									</label>
								</div>
								<div class="mb-3"><strong>Default Status</strong>
                    {foreach from=$defaultStatuses item=statusLabel key=status}
											<div class="checkbox">
												<label>
													<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
												</label>
											</div>
                    {/foreach}
								</div>
								<div class="mb-3"><strong>Open Statuses</strong>
                    {foreach from=$openStatuses item=statusLabel key=status}
											<div class="checkbox">
												<label>
													<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
												</label>
											</div>
                    {/foreach}
								</div>
								<div class="mb-3"><strong>Closed Statuses</strong>
                    {foreach from=$closedStatuses item=statusLabel key=status}
											<div class="checkbox">
												<label>
													<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
												</label>
											</div>
                    {/foreach}
								</div>
							</fieldset>
							<fieldset>
							<legend>Reporting Range</legend>
								<div class="mb-3">
									<label for="period" class="form-label">Period</label>
									<select name="period" id="period"{* onchange="$('#startDate').val('');$('#endDate').val('');"*} class="form-select">
										<option value="day" {if $period == 'day'}selected="selected"{/if}>Day</option>
										<option value="week" {if $period == 'week'}selected="selected"{/if}>Week</option>
										<option value="month" {if $period == 'month'}selected="selected"{/if}>Month</option>
										<option value="year" {if $period == 'year'}selected="selected"{/if}>Year</option>
									</select>
								</div>
								<div class="mb-3">
									<label for="startDate" class="form-label"> From</label>
									<input type="text" id="startDate" name="startDate" value="{$startDate}" size="8" class="form-control">
								</div>
								<div class="mb-3">
									<label for="endDate" class="form-label">To</label>
									<input type="text" id="endDate" name="endDate" value="{$endDate}" size="8" class="form-control">
							</div>
							</fieldset>
						<div class="mb-3">
							<input type="submit" name="submit" value="Update Filters" class="btn btn-primary">
						</div>
						</form>

<br>


			{* Display results as graph *}
			{if $chartPath}

				<legend>Chart</legend>

				<div id="chart">
					<img src="{$chartPath}" alt="Summary Report Chart">
				</div>

				<br>
			{/if}

			{* Display results in table*}

			<legend>Table</legend>

			<table id="summaryTable" class="table table-striped table-bordered">
				<thead>
					<tr>
						<th>Date</th>
						{foreach from=$statuses item=status}
							<th>{$status|translate}</th>
						{/foreach}
					</tr>
				</thead>
				<tbody>
					{foreach from=$periodData item=periodInfo key=periodStart}
						<tr>
							<td>
								{* Properly format the period *}
								{if $period == 'year'}
									{$periodStart|date_format:'%Y'}
								{elseif $period == 'month'}
									{$periodStart|date_format:'%h %Y'}
								{else}
									{$periodStart|date_format}
								{/if}
							</td>
							{foreach from=$statuses key=status item=statusLabel}
								<td><strong>{if $periodInfo.$status}{$periodInfo.$status}{else}0{/if}</strong></td>
							{/foreach}
						</tr>
					{/foreach}
				</tbody>
			</table>
		{/if}

		<form action="/MaterialsRequest/SummaryReport" method="get">
			<input type="hidden" name="period" value="{$period}">
			<input type="hidden" name="startDate" value="{$startDate}">
			<input type="hidden" name="endDate" value="{$endDate}">
			{foreach from=$statusFilter item=status}
				<input type="hidden" name="statusFilter[]" value="{$status}">
			{/foreach}
			<input type="submit" id="exportToExcel" name="exportToExcel" value="Export to Excel"  class="btn btn-outline-secondary">
		</form>

		{* Export to Excel option *}
	</div>

<script>
{literal}
	$("#startDate").datepicker();
	$("#endDate").datepicker();

{/literal}
</script>
	<script>
		{literal}
		$(document).ready(function(){
			$('#summaryTable').DataTable({
				"order": [[0, "asc"]],
				pageLength: 100
			});
		})

		{/literal}
	</script>