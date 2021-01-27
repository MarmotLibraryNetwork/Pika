{strip}
<div id="main-content" class="col-md-12">
	<h2>Manage Materials Requests</h2>
	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{/if}
	{if $loggedIn}
		<div id="materialsRequestFilters" class="accordion">
			<div class="panel panel-default">
			<div class="panel-heading">
				<div class="panel-title collapsed">
					<a href="#filterPanel" data-toggle="collapse" role="button">
						Filters
					</a>
				</div>
			</div>
			<div id="filterPanel" class="panel-collapse collapse">
				<div class="panel-body">

					<form action="/MaterialsRequest/ManageRequests" method="get">
						<fieldset class="fieldset-collapsible">
							<legend>Statuses to Show:</legend>
							<div class="form-group checkbox">
								<label for="selectAllStatusFilter">
									<input type="checkbox" name="selectAllStatusFilter" id="selectAllStatusFilter" onchange="Pika.toggleCheckboxes('.statusFilter', '#selectAllStatusFilter');">
									<strong>Select All</strong>
								</label>
							</div>
							<div class="form-group">
								{foreach from=$availableStatuses item=statusLabel key=status}
									<div class="checkbox">
										<label>
											<input type="checkbox" name="statusFilter[]" value="{$status}" {if in_array($status, $statusFilter)}checked="checked"{/if} class="statusFilter">{$statusLabel}
										</label>
									</div>
								{/foreach}
							</div>
						</fieldset>
						<fieldset class="form-group fieldset-collapsible">
							<legend>Date:</legend>
{*
								<label for="startDate">From</label> <input type="text" id="startDate" name="startDate" value="{$startDate}" size="8">
								<label for="endDate">To</label> <input type="text" id="endDate" name="endDate" value="{$endDate}" size="8">
*}
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
						<fieldset class="form-group fieldset-collapsible">
							<legend>Request IDs to Show (separated by commas):</legend>
							<div class="form-group">
								<label for="idsToShow">Request IDs</label> <input type="text" id="idsToShow" name="idsToShow" value="{$idsToShow}" size="60" class="form-control">
							</div>
						</fieldset>
						<fieldset class="form-group fieldset-collapsible">
							<legend>Format:</legend>
							<div class="form-group checkbox">
								<label for="selectAllFormatFilter">
									<input type="checkbox" name="selectAllFormatFilter" id="selectAllFormatFilter" onchange="Pika.toggleCheckboxes('.formatFilter', '#selectAllFormatFilter');">
									<strong>Select All</strong>
								</label>
							</div>
							<div class="form-group">
								{foreach from=$availableFormats item=formatLabel key=format}
									<div class="checkbox">
										<label><input type="checkbox" name="formatFilter[]" value="{$format}" {if in_array($format, $formatFilter)}checked="checked"{/if} class="formatFilter">{$formatLabel}</label>
									</div>
								{/foreach}
							</div>
						</fieldset>
						<fieldset class="fieldset-collapsible">
							<legend>Assigned To:</legend>
							<div class="form-group checkbox">
								<label for="showUnassigned">
									<input type="checkbox" name="showUnassigned" id="showUnassigned"{if $showUnassigned} checked{/if}>
									<strong>Unassigned</strong>
								</label>
							</div>
								<div class="form-group checkbox">
								<label for="selectAllAssigneesFilter">
									<input type="checkbox" name="selectAllAssigneesFilter" id="selectAllAssigneesFilter" onchange="Pika.toggleCheckboxes('.assigneesFilter', '#selectAllAssigneesFilter');">
									<strong>Select All</strong>
								</label>
							</div>
							<div class="form-group">
								{foreach from=$assignees item=displayName key=assigneeId}
									<div class="checkbox">
										<label>
											<input type="checkbox" name="assigneesFilter[]" value="{$assigneeId}" {if in_array($assigneeId, $assigneesFilter)}checked="checked"{/if} class="assigneesFilter">{$displayName}
										</label>
									</div>
								{/foreach}

							</div>
						</fieldset>

						<input type="submit" name="submit" value="Update Filters" class="btn btn-default">
					</form>

				</div>
			</div>
		</div>
		{if count($allRequests) > 0}
			<form id="updateRequests" method="post" action="/MaterialsRequest/ManageRequests" class="form form-horizontal">
				<table id="requestedMaterials" class="table order-column stripe table-hover">
					<thead>
						<tr>
							<th><input type="checkbox" name="selectAll" id="selectAll" onchange="Pika.toggleCheckboxes('.select', '#selectAll');"></th>
							{foreach from=$columnsToDisplay item=label}
								<th>{$label}</th>
							{/foreach}
							<th>&nbsp;</th> {* Action Buttons Column *}
						</tr>
					</thead>
					<tbody>
						{foreach from=$allRequests item=request}
							<tr>
								<td><input type="checkbox" name="select[{$request->id}]" class="select"></td>
								{foreach name="columnLoop" from=$columnsToDisplay item=label key=column}
									{if $column == 'format'}
										<td>
											{if in_array($request->format, array_keys($availableFormats))}
												{assign var="key" value=$request->format}
												{$availableFormats.$key}
											{else}
												{$request->format}
											{/if}
										</td>
									{elseif $column == 'abridged'}
										<td>{if $request->$column == 1}Yes{elseif $request->$column == 2}N/A{else}No{/if}</td>
									{elseif $column == 'about' || $column == 'comments'}
										<td>
											{if !empty($request->$column)}
												<textarea cols="30" rows="4" readonly disabled>
												{* TODO: use truncate modifier? *}
													{$request->$column}
											</textarea>
											{/if}
										</td>
									{elseif $column == 'status'}
										<td>{$request->statusLabel|translate}</td>
									{elseif $column == 'dateCreated' || $column == 'dateUpdated'}
										{* Date Columns*}
										<td>{$request->$column|date_format}</td>
									{elseif $column == 'createdBy'}
										<td>{$request->lastname}, {$request->firstname}<br>{$request->barcode}</td>

									{elseif $column == 'emailSent' || $column == 'holdsCreated' || $column == 'illItem'}
										{* Simple Boolean Columns *}
										<td>{if $request->$column}Yes{else}No{/if}</td>

									{elseif $column == 'email'}
										<td>{$request->email}</td>
									{elseif $column == 'placeHoldWhenAvailable'}
										<td>{if $request->$column}Yes{if $request->location} - {$request->location}{/if}{else}No{/if}</td>
									{elseif $column == 'holdPickupLocation'}
										<td>
											{$request->getHoldLocationName($request->holdPickupLocation)}
										</td>
									{elseif $column == 'bookmobileStop'}
										<td>{$request->bookmobileStop}</td>
									{elseif $column == 'assignedTo'}
										<td>{$request->assignedTo}</td>
{*
									{elseif $column == 'id'}
										<td>{$request->id}</td>
									{elseif $column == 'title'}
										<td>{$request->title}</td>
									{elseif $column == 'author'}
										<td>{$request->author}</td>
									{elseif $column == 'ageLevel'}
										<td>{$request->ageLevel}</td>
									{elseif $column == 'isbn'}
										<td>{$request->isbn}</td>
									{elseif $column == 'oclcNumber'}
										<td>{$request->oclcNumber}</td>
									{elseif $column == 'publisher'}
										<td>{$request->publisher}</td>
									{elseif $column == 'publicationYear'}
										<td>{$request->publicationYear}</td>
									{elseif $column == 'articleInfo'}
										<td>{$request->articleInfo}</td>
									{elseif $column == 'phone'}
										<td>{$request->phone}</td>
									{elseif $column == 'season'}
										<td>{$request->season}</td>
									{elseif $column == 'magazineTitle'}
										<td>{$request->magazineTitle}</td>
									{elseif $column == 'upc'}
										<td>{$request->upc}</td>
									{elseif $column == 'issn'}
										<td>{$request->issn}</td>
									{elseif $column == 'bookType'}
										<td>{$request->bookType}</td>
									{elseif $column == 'subFormat'}
										<td>{$request->subFormat}</td>
									{elseif $column == 'magazineDate'}
										<td>{$request->magazineDate}</td>
									{elseif $column == 'magazineVolume'}
										<td>{$request->magazineVolume}</td>
									{elseif $column == 'magazinePageNumbers'}
										<td>{$request->magazinePageNumbers}</td>
									{elseif $column == 'magazineNumber'}
										<td>{$request->magazineNumber}</td>
*}
									{else}
										{* All columns that can be displayed with out special handling *}
										<td>{$request->$column}</td>
									{/if}
								{/foreach}
								<td>
									<div class="btn-group btn-group-vertical btn-group-sm">
										<button type="button" onclick="Pika.MaterialsRequest.showMaterialsRequestDetails('{$request->id}', true)" class="btn btn-sm btn-info">Details</button>
										<button type="button" onclick="Pika.MaterialsRequest.updateMaterialsRequest('{$request->id}')" class="btn btn-sm btn-primary">Update&nbsp;Request</button>
									</div>
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if in_array('library_material_requests', $userRoles)}
					<div id="materialsRequestActions">
						<div class="row form-group">
							<div class="col-sm-4">
								<label for="newAssignee" class="control-label">Assign selected to:</label>
							</div>
							<div class="col-sm-8">
								<div class="input-group">
									{if $assignees}
										<select name="newAssignee" id="newAssignee" class="form-control">
											<option value="unselected">Select One</option>
											<option value="unassign">Un-assign (remove assignee)</option>

											{foreach from=$assignees item=displayName key=assigneeId}
												<option value="{$assigneeId}">{$displayName}</option>
											{/foreach}

										</select>
										<span class="btn btn-sm btn-primary input-group-addon" onclick="return Pika.MaterialsRequest.assignSelectedRequests();">Assign Selected Requests</span>
									{else}
										<span class="text-warning">No Valid Assignees Found</span>
									{/if}
								</div>
							</div>
						</div>
						<div class="row form-group">
							<div class="col-sm-4">
								<label for="newStatus" class="control-label">Change status of selected to:</label>
							</div>
							<div class="col-sm-8">
								<div class="input-group">
									<select name="newStatus" id="newStatus" class="form-control">
										<option value="unselected">Select One</option>
										{foreach from=$availableStatuses item=statusLabel key=status}
											<option value="{$status}">{$statusLabel}</option>
										{/foreach}
									</select>
									<span class="btn btn-sm btn-primary input-group-addon" onclick="return Pika.MaterialsRequest.updateSelectedRequests();">Update Selected Requests</span>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-xs-12">
								<input class="btn btn-sm btn-default" type="submit" name="exportSelected" value="Export Selected To Excel" onclick="return Pika.MaterialsRequest.exportSelectedRequests();">
							</div>
						</div>
					</div>
				{/if}
			</form>
		{else}
			<div class="alert alert-info">There are no materials requests that meet your criteria.</div>
		{/if}
	{/if}
</div>
{/strip}
	<script type="text/javascript">
		{literal}
		$(document).ready(function(){
			$('#requestedMaterials').DataTable({
				"order": [[0, "asc"]],
				pageLength: 25
			});
		})

		{/literal}
	</script>
