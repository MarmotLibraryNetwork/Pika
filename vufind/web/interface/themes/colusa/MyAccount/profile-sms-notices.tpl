{strip}
	<div class="panel active">
		<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#smsPanel">
			<div class="panel-heading">
				<div class="panel-title">
					SMS Settings
				</div>
			</div>
		</a>
		<div id="smsPanel" class="panel-collapse collapse in">
			<div class="panel-body">
				{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
				<form action="" method="post" class="form-horizontal">
					<input type="hidden" name="updateScope" value="contact">
					<input type="hidden" name="profileUpdateAction" value="updateSms">
					<div class="form-group">
						<div class="col-xs-4"><label for="smsNotices">{translate text='Receive SMS/Text Messages'}:</label></div>
						<div class="col-xs-8">
							{if $edit == true && $canUpdateContactInfo == true}
								<input type="checkbox" name="smsNotices" id="smsNotices" {if $profile->mobileNumber}checked='checked'{/if} data-switch="">
								<p class="help-block alert alert-warning">
									SMS/Text Messages are sent <strong>in addition</strong> to postal mail/e-mail/phone alerts. <strong>Message and data rates may apply.</strong>
									<br><br>
									To sign up for SMS/Text messages, you must opt-in above and enter your Mobile (cell phone) number below.
									<br><br>
									<strong>To opt-out from SMS Alerts, U.S.-based patrons can send a text message with the word STOP, STOP ALL, END, QUIT, CANCEL, or
										UNSUBSCRIBE to 82453 or 35143 from the mobile phone number specified during the opt-in process.</strong>
									<br><br>
									<a href="https://www.saclibrarycatalog.org/smsterms~S49" data-title="SMS Notice Terms" target="_blank">View Terms and Conditions</a>
								</p>
							{else}

							{/if}
						</div>
					</div>
					<div class="form-group">
						<div class="col-xs-4"><label for="mobileNumber">{translate text='Mobile Number'}:</label></div>
						<div class="col-xs-8">
							{if $edit == true && $canUpdateContactInfo == true}
								<input type="tel" name="mobileNumber" id="mobileNumber" value="{$profile->mobileNumber}" class="form-control">
							{else}
								{$profile->mobileNumber}
							{/if}
						</div>
					</div>

					{if !$offline && $edit == true}
						<div class="form-group">
							<div class="col-xs-8 col-xs-offset-4">
								<input type="submit" value="Update SMS Settings" name="updateContactInfo" class="btn btn-sm btn-primary">
							</div>
						</div>
					{/if}
				</form>
			</div>
		</div>
	</div>
{/strip}