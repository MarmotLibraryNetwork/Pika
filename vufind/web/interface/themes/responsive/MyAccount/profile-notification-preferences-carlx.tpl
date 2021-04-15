{strip}
	{* CarlX Notification Options *}

	<div class="form-group">
		<div class="col-xs-4"><strong>{translate text='Email notices'}:</strong></div>
		<div class="col-xs-8">
			{if $edit == true && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm" data-toggle="buttons">
					<label for="sendEmail" class="btn btn-sm btn-default {if $profile->notices == 'send email'}active{/if}"><input type="radio" value="send email" id="sendEmail" name="notices" {if $profile->notices == 'send email'}checked="checked"{/if}> Send Email</label>
					<label for="dontSendEmail" class="btn btn-sm btn-default {if $profile->notices == 'do not send email'}active{/if}"><input type="radio" value="do not send email" id="dontSendEmail" name="notices" {if $profile->notices == 'do not send email'}checked="checked"{/if}> Do not send email</label>
					<label for="optOut" class="btn btn-sm btn-default {if $profile->notices == 'opted out'}active{/if}"><input type="radio" value="opted out" id="optOut" name="notices" {if $profile->notices == 'opted out'}checked="checked"{/if}> Opt-out</label>
				</div>
			{else}
				{$profile->notices}
			{/if}
		</div>
	</div>


	<div class="form-group">
		<div class="col-xs-4"><label for="emailReceiptFlag" class="control-label">{translate text='Email receipts for checkouts and renewals'}:</label></div>
		<div class="col-xs-8">
			{if $edit == true}
				<input type="checkbox" name="emailReceiptFlag" id="emailReceiptFlag" {if $profile->emailReceiptFlag==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->emailReceiptFlag==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

	<div class="form-group">
		<div class="col-xs-4"><label for="phoneType" class="">{translate text='Phone Carrier for SMS notices'}:</label></div>
		<div class="col-xs-8">
			{if $edit == true && $canUpdateContactInfo == true}
				<select name="phoneType" id="phoneType" class="form-control">
					{if count($phoneTypes) > 0}
						{foreach from=$phoneTypes item=phoneTypeLabel key=phoneType}
							<option value="{$phoneType}" {if $phoneType == $profile->phoneType}selected="selected"{/if}>{$phoneTypeLabel}</option>
						{/foreach}
					{else}
						<option></option>
					{/if}
				</select>
			{else}
				{assign var=i value=$profile->phoneType}
				{$phoneTypes[$i]}
			{/if}
		</div>
	</div>


	<div class="form-group">
		<div class="col-xs-4"><label for="availableHoldNotice" class="control-label">{translate text='SMS notices for available holds'}:</label></div>
		<div class="col-xs-8">
			{if $edit == true}
				<input type="checkbox" name="availableHoldNotice" id="availableHoldNotice" {if $profile->availableHoldNotice==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->availableHoldNotice==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

	<div class="form-group">
		<div class="col-xs-4"><label for="comingDueNotice" class="control-label">{translate text='SMS notices for due date reminders'}:</label></div>
		<div class="col-xs-8">
			{if $edit == true}
				<input type="checkbox" name="comingDueNotice" id="comingDueNotice" {if $profile->comingDueNotice==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->comingDueNotice==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

{/strip}