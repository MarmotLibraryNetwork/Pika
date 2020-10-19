{strip}
<form method="post" action="" id="overdriveFreezeHoldPromptsForm" class="form">
	<div>
		<input type="hidden" name="overDriveId" value="{$overDriveId}">
		<input type="hidden" name="patronId" id="patronId" value="{$patronId}">

		{if $promptForEmail}
			<div class="form-group">
				<label for="overDriveEmail" class="control-label">{translate text="Enter an e-mail to be notified when the title is ready for you."}</label>
				<input type="text" class="email form-control" name="overDriveEmail" value="{$overDriveEmail}" size="40" maxlength="250">
				</div>
			<div class="checkbox">
				<label for="rememberOverdriveEmail" class="control-label"><input type="checkbox" name="rememberOverdriveEmail" id="rememberOverdriveEmail"> Remember this e-mail.</label>
			</div>
			<div class="alert alert-info">
				<p>To use this email address for future hold requests, click <em>Remember this e-mail</em> above.</p>
				<p>To remove the email portion of this form, visit the <a href="/MyAccount/Profile">Account Settings</a> page and turn off <em>{translate text='Set Notification Email While Placing Hold'}</em> in the OverDrive Options section.</p>
			</div>
		{else}
			<input type="hidden" name="overDriveEmail" value="{$overDriveEmail}">
		{/if}
	</div>

	<div class="form-group">
		<label for="thawDate">Select the date when you want the hold {translate text="thawed"}.</label>
		<input type="text" name="thawDate" id="thawDate" class="form-control input-sm datePika"{if $thawDate} value="{$thawDate}"{/if}>
	</div>
		<p class="alert alert-info">
			If a date is not selected, the hold will be {translate text="frozen"} until you {translate text="thaw"} it.
		</p>
	<script	type="text/javascript">
{literal}
$(function(){
	$(".form").validate({
		submitHandler: function(){
			Pika.OverDrive.processFreezeOverDriveHoldPrompts()
		}
	});
	$( "#thawDate" ).datepicker({
		format: "mm-dd-yyyy",
		startDate: Date(),
		orientation:"top"
	});
});
{/literal}
	</script>
</form>
{/strip}