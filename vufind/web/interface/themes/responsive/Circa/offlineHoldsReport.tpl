{strip}
	<div id="page-content" class="content">
		{if $error}<p class="error">{$error}</p>{/if}
		<div id="sidebar">
			{* Report filters *}
			<div class="sidegroup">
				<h4>Report Filters</h4>
				<div class="sidegroupContents">
					<form id="offlineHoldsFilter">
						<div  class="form-horizontal">
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
							</div>							<br>
							<div class="form-group">
								<input type="submit" name="updateFilters" value="Update Filters" class="btn btn-primary"/>
							</div>

						</div>
					</form>
				</div>
			</div>
		</div>

		<div id="main-content">
			<h2>Offline Holds</h2>
			{if count($offlineHolds) > 0}
				<table class="citation tablesorter" id="offlineHoldsReport" >
					<thead>
						<tr><th>Patron Barcode</th><th>Record Id</th><th>Title</th><th>Date Entered</th><th>Status</th><th>Notes</th></tr>
					</thead>
					<tbody>
						{foreach from=$offlineHolds item=offlineHold}
							{* TODO Update this to work with multi-ils installations*}
							<tr><td>{$offlineHold.patronBarcode}</td><td>{$offlineHold.bibId}</td><td><a href="{$path}/Record/{$offlineHold.bibId}">{$offlineHold.title}</a></td><td>{$offlineHold.timeEntered|date_format}</td><td>{$offlineHold.status}</td><td>{$offlineHold.notes}</td></tr>
						{/foreach}
					</tbody>
				</table>
			{else}
				<p>There are no offline holds to display.</p>
			{/if}
		</div>
	</div>
	<script	type="text/javascript">
		{literal}
		$(function() {
			$("#offlineHoldsReport").tablesorter({cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader', widgets:['zebra', 'filter'] });
		});
		{/literal}
	</script>
{/strip}