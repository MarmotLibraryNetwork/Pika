<div id="main-content">

	<span class='availableHoldsNoticePlaceHolder'></span>

	<h1 role="heading" aria-level="1" class="h2">My {translate text='Materials_Request_alt'}s</h1>
	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{else}
		<div id="materialsRequestSummary" class="alert alert-info">
			You have used <strong>{$requestsThisYear}</strong> of your {$maxRequestsPerYear} yearly {translate text='materials request'}s. {'materials_request_short'|translate|capitalize}s are counted on a rolling year basis from the date a {'materials_request_short'|translate} was made. Customers are limited to {$maxActiveRequests} active {translate text='materials_request_short'}s at a time. You currently have <strong>{$openRequests}</strong> active {translate text='materials_request_short'}s.
		</div>
		<div id="materialsRequestFilters">
			<legend>Filters:</legend>
			<form action="/MaterialsRequest/MyRequests" method="get" class="form-inline">
				<div>
					<div class="form-group">
						<label class="control-label">Show:</label>
						<label for="openRequests" class="radio-inline">
							{*<input type="radio" id="openRequests" name="requestsToShow" value="openRequests" {if $showOpen}checked="checked"{/if}> Open {translate text='materials_request_short'|capitalize}s*}
							<input type="radio" id="openRequests" name="requestsToShow" value="openRequests" {if $showOpen}checked="checked"{/if}> Open {'materials_request_short'|translate|capitalize}s
						</label>
						<label for="allRequests" class="radio-inline">
							<input type="radio" id="allRequests" name="requestsToShow" value="allRequests" {if !$showOpen}checked="checked"{/if}> All {'materials_request_short'|translate|capitalize}s
						</label>
					</div>
					<div class="form-group">
						<input type="submit" name="submit" value="Update Filters" class="btn btn-sm btn-default">
					</div>
				</div>
			</form>
		</div>
		<br>
		{if count($allRequests) > 0}
			<table id="requestedMaterials" class="table stripe table-condensed">
				<thead>
					<tr>
						<th>Title</th>
						<th>Author</th>
						<th>Format</th>
						<th>Status</th>
						<th>Created</th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$allRequests item=request}
						<tr>
							<td>{$request->title}</td>
							<td>{$request->author}</td>
							<td>{$request->format}</td>
							<td>{$request->statusLabel|translate}</td>
							<td><span data-date="{$request->dateCreated}">{$request->dateCreated|date_format}</span></td>
							<td>
								<a role="button" onclick='Pika.MaterialsRequest.showMaterialsRequestDetails("{$request->id}", false)' class="btn btn-info btn-sm">Details</a>
								{if $request->status == $defaultStatus}
								<a role="button" onclick="return Pika.MaterialsRequest.cancelMaterialsRequest('{$request->id}');" class="btn btn-danger btn-sm">Cancel {'materials_request_short'|translate|capitalize}</a>
								{/if}
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<div class="alert alert-warning">There are no {translate text='materials request'}s that meet your criteria.</div>
		{/if}
		<div id="createNewMaterialsRequest"><a href="/MaterialsRequest/NewRequest" class="btn btn-primary btn-sm">Submit a New {translate text='Materials_Request_alt'}</a></div>
	{/if}
</div>
<script>
	{literal}
	$(document).ready(function(){
		$.fn.dataTable.ext.order['dom-date'] = function (settings, col){
			return this.api().column(col, {order: 'index'}).nodes().map(function (td, i){
				return $('span', td).attr("data-date");
			});
		}
		$('#requestedMaterials').DataTable({
			"columns":[
				null,
				null,
				null,
				null,
				{"orderDataType": "dom-date"},
				{"orderable": false}
			],
			"order": [[0, "asc"]],
			pageLength: 100
		});
	})
	{/literal}
</script>
