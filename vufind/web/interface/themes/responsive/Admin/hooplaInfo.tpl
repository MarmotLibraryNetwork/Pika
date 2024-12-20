{strip}
	<div id="page-content" class="col-tn-12">
		<h1 role="heading" aria-level="1" class="h2">Hoopla API Info</h1>
		{if $isHooplaEnabled}

		<h2 class="h4">Report Filters</h2>
		<div class="navbar row">
			<form id="HooplaInfoFilter">
				<div class="form-horizontal">
					{if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles)|| in_array('libraryManager', $userRoles))}
						<div class="form-group">
							<label for="startDate" class="control-label col-sm-3">Start Date</label>
							<div class="input-group input-append date controls col-sm-3" id="startDatePicker">
								<input type="text" name="startDate" id="startDate" size="10" value="{$startDate|date_format:'%m/%d/%Y'}"
								       data-provide="datepicker" data-date-format="mm/dd/yyyy" data-date-end-date="0d"
								       class="form-control">
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"
									      onclick="$('#startDate').focus().datepicker('show')"
									      aria-hidden="true">
									</span>
								</span>
							</div>
						</div>
						<div class="form-group">
							<label for="endDate" class="control-label col-sm-3">End Date</label>
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
          {/if}
					<div class="form-group">
						<label for="hooplaId" class="control-label col-sm-3">Hoopla Record Id</label>
						<div class="col-sm-3">
						<input type="text" name="hooplaId" id="hooplaId" class="form-control"{if ctype_digit($smarty.get.hooplaId)} value="{$smarty.get.hooplaId}"{/if}>
						</div>
					</div>
					{if $userRoles && in_array('opacAdmin', $userRoles)}
						<div class="form-group">
							<label for="hooplaId" class="control-label col-sm-3">Hoopla Library Id</label>
							<div class="col-sm-3">
							<input type="text" name="hooplaLibraryId" id="hooplaId" class="form-control"{if ctype_digit($smarty.get.hooplaLibraryId)} value="{$smarty.get.hooplaLibraryId}"{/if}>
							</div>
						</div>
					{/if}

					<button class="btn btn-primary" type="submit">Go</button>
				</div>
			</form>
		</div>

		<div id="main-content">
			{if !empty($hooplaRecordData)}
				<div class="row">
					<div class="col-tn-3"><strong>Using Hoopla Library Id :</strong></div>
					<div class="col-tn-3">{$hooplaLibraryId}</div>
					<div class="col-tn-6"><div class="alert {if $success}alert-success{else}alert-warning{/if}">{$message}</div></div>
				</div>
{*				<div class="row">
					<div class="col-tn-12">

					</div>
				</div>*}
				<div class="row">
				<div class="col-tn-12">
					<h3 class="h4">Content Call</h3>
					<pre>
						{$hooplaRecordData}
					</pre>
				</div>
			</div>
			{/if}
			{if !empty($hooplaContentInfo)}
				<div class="row">
					<div class="col-tn-12">
						<h3 class="h4">Content Info Call</h3>
						<pre>{$hooplaContentInfo}</pre>
					</div>
				</div>
			{/if}
			{if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles)|| in_array('libraryManager', $userRoles))}
					<div class="row">
						<div class="col-tn-12">
							<h3 class="h4">Hoopla Library Statistics</h3>
							<table class="table stripe" id="hooplaCheckoutsReport">
								<thead>
								<tr>
									<th>Hoopla Library Id</th>
									<th>Library</th>
									<th>Check Outs</th>
									<th>Patron Count</th>
								</tr>
								</thead>
								<tbody>
								{foreach from=$hooplaLibraryCheckouts item=checkout}
									<tr>
										<td>{$checkout.hooplaLibraryId}</td>
										<td>{$checkout.libraryName}</td>
										<td>{$checkout.checkouts}</td>
										<td>{$checkout.patrons}</td>
									</tr>
								{/foreach}
								</tbody>
							</table>
						</div>
					</div>
				<script>
						{literal}
						$(function(){
							$('#hooplaCheckoutsReport').DataTable({
								"order": [[1, "asc"]],
								pageLength: 100
							});
						})
						{/literal}
				</script>
			{/if}

		</div>
		{else}
		<div class="alert alert-warning">Hoopla API is not enabled.</div>
	{/if}
	</div>
{/strip}