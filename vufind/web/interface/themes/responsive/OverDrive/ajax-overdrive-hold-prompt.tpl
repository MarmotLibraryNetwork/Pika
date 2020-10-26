{strip}
<form method="post" action="" id="overDriveHoldPromptsForm" class="form">
	<div>
		<input type="hidden" name="overdriveId" value="{$overDriveId}">
		{if count($overDriveUsers) > 1} {* Linked Users contains the active user as well*}
			<div class="form-group">
				<label class="control-label" for="patronId">{translate text="Place hold for account"}: </label>
				<div class="controls">
					<select name="patronId" id="patronId" class="form-control">
						{foreach from=$overDriveUsers item=tmpUser}
							<option value="{$tmpUser->id}" {if $location->selected == "selected"}selected="selected"{/if}>{$tmpUser->displayName} - {$tmpUser->getHomeLibrarySystemName()}</option>
						{/foreach}
					</select>
				</div>
			</div>
		{else}
			<input type="hidden" name="patronId" id="patronId" value="{$patronId}">
		{/if}

		{if $promptForEmail}
			<div class="form-group">
				<label for="overDriveEmail" class="control-label">{translate text="Enter an e-mail to be notified when the title is ready for you."}</label>
				<input type="text" class="email form-control" name="overDriveEmail" id="overDriveEmail" value="{$overDriveEmail}" size="40" maxlength="250">
			</div>
			<div class="form-group">
				<label for="rememberOverDriveEmail" class="control-label checkbox"><input type="checkbox" name="rememberOverDriveEmail" id="rememberOverDriveEmail"> Remember this e-mail.</label>
			</div>
			<div class="alert alert-info">
				<p>To use this email address for future hold requests, click <em><strong>Remember this e-mail</strong></em> above.</p>
				<p>To automatically skip this prompt for future hold requests, visit the <a href="/MyAccount/Profile">Account Settings</a> page and turn off <em><strong>{translate text='Set Notification Email While Placing Hold'}</strong></em> in the OverDrive Options section.</p>
			</div>
		{else}
			<input type="hidden" name="overDriveEmail" value="{$overDriveEmail}">
		{/if}
	</div>
</form>
{/strip}