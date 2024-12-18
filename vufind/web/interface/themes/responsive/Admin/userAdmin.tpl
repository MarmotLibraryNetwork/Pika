{strip}
<div id="main-content" class="col-tn-12 col-xs-12">
	<h1>User Administration</h1>
	<hr>
	<form name="resetDisplayName" method="post" enctype="multipart/form-data" class="form-horizontal">
		<fieldset>
			<legend>Reset Display Name</legend>

        {if $error}
					<div class="alert alert-danger">{$error}</div>
        {/if}
        {if $success}
					<div class="alert alert-success">{$success}</div>
        {/if}

			<input type="hidden" name="userAction" value="resetDisplayName">
			<div class="row form-group">
				<label for="barcode" class="col-sm-2 control-label">Barcode: </label>
				<div class="col-sm-6">
					<input type="text" name="barcode" id="barcode" class="form-control"{if $barcode} value="{$barcode}"{/if}>
				</div>
				<div class="col-sm-2">
					<button type="submit" class="btn btn-primary">Reset User's display name</button>
				</div>
			</div>
			<div class="form-group">
			</div>
		</fieldset>
	</form>

  {if in_array('userAdmin', $userRoles)}
		<form name="manageDuplicateUsers" method="post" enctype="multipart/form-data" class="form-horizontal">
			<fieldset>
				<legend>Manage Duplicate-Barcode Accounts</legend>

	        {if $duplicateError}
						<div class="alert alert-danger">{$duplicateError}</div>
	        {/if}
	        {if $duplicateSuccess}
						<div class="alert alert-success">{$duplicateSuccess}</div>
	        {/if}

				<input type="hidden" name="userAction" value="showDuplicates">
				<div class="row form-group">
					<label for="barcode" class="col-sm-2 control-label">Barcode: </label>
					<div class="col-sm-6">
						<input type="text" name="barcode" id="barcode" class="form-control"{if $duplicateBarcode} value="{$duplicateBarcode}"{/if}>
					</div>
					<div class="col-sm-2">
						<button type="submit" class="btn btn-primary">Look up Accounts</button>
					</div>
				</div>
				<div class="form-group">
				</div>
			</fieldset>
		</form>

		{if !empty($duplicateUsers)}
			<table class="table-condensed">
			<tr>
				<th>#</th>
				<th>First Name</th>
				<th>Last Name</th>
				<th>Created</th>
				<th>ILS user ID</th>
				<th>Home Library</th>
				<td></td> {* Can't be <th>, for accessiblity. "Table header elements should have visible text. Ensure that the table header can be used by screen reader users. If the element is not a header, marking it up with a `td` is more appropriate." *}
				<td></td>
				<td></td>
			</tr>
				{foreach from=$duplicateUsers item=duplicateUser name=loop}
						<tr style="border-top: 1px solid #ddd">
							<td><strong>{$smarty.foreach.loop.iteration}</strong></td>
							<td>{$duplicateUser->firstname}</td>
							<td>{$duplicateUser->lastname}</td>
							<td>{$duplicateUser->created|date_format:'%m/%d/%Y'}</td>
							<td>{$duplicateUser->ilsUserId}</td>
							<td>{$duplicateUser->getHomeLibrarySystemName()}</td>
{*							<td>{if $duplicateUser->getAccountProfile()}Password set{/if}</td>*}
							<td>{if $duplicateUser->passwordSet}Password set{/if}</td>
							<td>{if $duplicateUser->hasStaffPtypes()}Staff Ptype{/if}</td>
{*							<td>{if $duplicateUser->getAccountProfile()->usingPins() && !empty($duplicateUser->password)}Password set{/if}</td>*}
							<td>
								{if $duplicateUser->safeToDelete}
									<form method="post" enctype="multipart/form-data">
										<input type="hidden" name="userAction" value="deleteDuplicate">
										<input type="hidden" name="userId" value="{$duplicateUser->id}">
										<button class="btn btn-sm btn-danger" onclick="if(confirm('Confirm delete user account? THIS CAN NOT BE UNDONE!')) $(this).parent('form').submit(); return false">Delete Pika Account</button>
									</form>
								{/if}
							</td>
						</tr>
						<tr>
							<td>Lists <span class="badge{if $duplicateUser->userListCount} badge-danger{/if}">{$duplicateUser->userListCount}</span></td>
							<td>Linked Users <span class="badge{if $duplicateUser->linkedUsersCount} badge-danger{/if}">{$duplicateUser->linkedUsersCount}</span></td>
							<td>Reviews <span class="badge{if $duplicateUser->userReviewsCount} badge-danger{/if}">{$duplicateUser->userReviewsCount}</span></td>
							<td>Reading History <span class="badge{if $duplicateUser->readingHistoryCount} badge-danger{/if}">{$duplicateUser->readingHistoryCount}</span></td>
							<td>Not Interested <span class="badge{if $duplicateUser->notInterestedCount} badge-danger{/if}">{$duplicateUser->notInterestedCount}</span></td>
							<td>Pika Roles <span class="badge{if $duplicateUser->roleCount} badge-danger{/if}">{$duplicateUser->roleCount}</span></td>
							<td>Tags <span class="badge{if $duplicateUser->userTagsCount} badge-danger{/if}">{$duplicateUser->userTagsCount}</span></td>
						</tr>
				{/foreach}
			</table>
		{/if}
	  <form name="checkUserReadingHistoryActions" method="post" enctype="multipart/form-data" class="form-horizontal">
		  <fieldset>
			  <legend>Check User Reading History Actions <small>(after {$readingHistoryLogStart|date_format})</small></legend>

			  <input type="hidden" name="userAction" value="showReadingHistoryActions">
			  <div class="row form-group">
				  <label for="barcode" class="col-sm-2 control-label">Barcode: </label>
				  <div class="col-sm-6">
					  <input type="text" name="barcode" id="barcode" class="form-control"{if $readingHistoryBarcode} value="{$readingHistoryBarcode}"{/if}>
				  </div>
				  <div class="col-sm-2">
					  <button type="submit" class="btn btn-primary">Look up Reading History Actions</button>
				  </div>
			  </div>
			  <div class="form-group">
			  </div>
		  </fieldset>
	  </form>
	  {if !empty($readingHistoryActions) && $readingHistorySuccess}
			<table class="table-responsive table-striped dataTable">
				<thead>
				<tr>
					<th>Date</th>
					<th>Action</th>
				</tr>
				</thead>
		  {foreach from=$readingHistoryActions item=historyAction}
				<tr>
					<td><strong>{$historyAction.date|date_format:'%m/%d/%Y %I:%M:%S'}</strong></td>
					<td>{$historyAction.action}</td>
				</tr>
			{/foreach}
			</table>
	  {elseif $readingHistoryError}
		  <p class="alert-warning">The user account with barcode {$readingHistoryBarcode} has not enabled/disabled/cleared their reading history.</p>
	  {/if}
  {/if}
</div>
{/strip}