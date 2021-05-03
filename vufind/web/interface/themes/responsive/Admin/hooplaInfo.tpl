{strip}
	<div id="page-content" class="col-tn-12">
		<h4>Report Filters</h4>
		<div class="navbar row">
			<form id="HooplaInfoFilter">
				<div class="form-horizontal">
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
					<div class="form-group">
						<label for="hooplaId" class="control-label col-sm-2">Hoopla Record Id</label>
						<div class="col-sm-3">
						<input type="text" name="hooplaId" id="hooplaId" class="form-control"{if $smarty.get.hooplaId} value="{$smarty.get.hooplaId}"{/if}>
						</div>
					</div>

					<button class="btn btn-primary" type="submit">Go</button>
			</form>
		</div>

		<div id="main-content">
			<h2>Hoopla API Info</h2>
			{if !empty($hooplaRecordData)}
			<div class="row">
				<div class="col-tn-12">
					<pre>
						{$hooplaRecordData}
					</pre>
				</div>
			</div>
			{/if}
			<div class="row">
				<div class="col-tn-12">
					<table class="table stripe" id="hooplaCheckoutsReport">
						<thead>
						<tr>
							<th>Hoopla Library Id</th>
							<th>Library</th>
							<th>Check Outs</th>
						</tr>
						</thead>
						<tbody>
            {foreach from=$hooplaLibraryCheckouts item=checkout}
							<tr>
								<td>{$checkout.hooplaLibraryId}</td>
								<td>{$checkout.libraryName}</td>
								<td>{$checkout.checkouts}</td>
							</tr>
            {/foreach}
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">
		{literal}
		$(document).ready(function(){
			$('#hooplaCheckoutsReport').DataTable({
				"order": [[1, "asc"]],
				pageLength: 100
			});
		})

		{/literal}
	</script>
{/strip}