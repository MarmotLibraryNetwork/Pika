{strip}
  {if $promptForEmail}
		<div class="mb-3">
			<label for="overDriveEmail" class="form-label">{translate text="Enter an e-mail to be notified when the title is ready for you."}{* <span class="required-input">*</span>*}</label>
			<input type="text" class="email form-control{* required*}" name="overDriveEmail" id="overDriveEmail" value="{$overDriveEmail}" size="40" maxlength="250"{* aria-required="true"*}>
		</div>
		<div class="mb-3">
			<label for="rememberOverDriveEmail" class="form-label checkbox"><input type="checkbox" name="rememberOverDriveEmail" id="rememberOverDriveEmail"> {translate text="Save this email as my default for hold notifications"}</label>
		</div>
		<div class="alert alert-info">
			<p>To use this email address for future hold requests, click <em><strong>{translate text="Save this email as my default for hold notifications"}</strong></em> above.</p>
			<p>To automatically skip this prompt for future hold requests, visit the <a href="/MyAccount/Profile">Account Settings</a> page and turn off <em><strong>{translate text='Set Notification Email While Placing Hold'}</strong></em> in the OverDrive Options section.</p>
		</div>
  {else}
		<input type="hidden" name="overDriveEmail" value="{$overDriveEmail}">
  {/if}
{/strip}