{strip}
	{* CarlX Notification Options *}

	<div class="row mb-3">
		<div class="col-sm-4"><strong>{translate text='Email notices'}:</strong></div>
		<div class="col-sm-8">
			{if !$offline && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm">
					<input type="radio" class="btn-check" value="send email" id="sendEmail" name="notices" autocomplete="off"{if $profile->notices == 'send email'} checked="checked"{/if}><label for="sendEmail" class="btn btn-sm btn-outline-secondary">Send Email</label>
					<input type="radio" class="btn-check" value="do not send email" id="dontSendEmail" name="notices" autocomplete="off"{if $profile->notices == 'do not send email'} checked="checked"{/if}><label for="dontSendEmail" class="btn btn-sm btn-outline-secondary">Do not send email</label>
					<input type="radio" class="btn-check" value="opted out" id="optOut" name="notices" autocomplete="off"{if $profile->notices == 'opted out'} checked="checked"{/if}><label for="optOut" class="btn btn-sm btn-outline-secondary">Opt-out</label>
				</div>
			{else}
				{$profile->notices}
			{/if}
		</div>
	</div>


	<div class="row mb-3">
		<div class="col-sm-4"><label for="emailReceiptFlag" class="form-label">{translate text='Email receipts for checkouts and renewals'}:</label></div>
		<div class="col-sm-8">
			{if !$offline}
				<input type="checkbox" name="emailReceiptFlag" id="emailReceiptFlag" {if $profile->emailReceiptFlag==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->emailReceiptFlag==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

	<div class="row mb-3">
		<div class="col-sm-4"><label for="phoneType" class="">{translate text='Phone Carrier for SMS notices'}:</label></div>
		<div class="col-sm-8">
			{if !$offline && $canUpdateContactInfo == true}
				<select name="phoneType" id="phoneType" class="form-select">
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


	<div class="row mb-3">
		<div class="col-sm-4"><label for="availableHoldNotice" class="form-label">{translate text='SMS notices for available holds'}:</label></div>
		<div class="col-sm-8">
			{if !$offline}
				<input type="checkbox" name="availableHoldNotice" id="availableHoldNotice" {if $profile->availableHoldNotice==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->availableHoldNotice==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

	<div class="row mb-3">
		<div class="col-sm-4"><label for="comingDueNotice" class="form-label">{translate text='SMS notices for due date reminders'}:</label></div>
		<div class="col-sm-8">
			{if !$offline}
				<input type="checkbox" name="comingDueNotice" id="comingDueNotice" {if $profile->comingDueNotice==1}checked='checked'{/if} data-switch="">
			{else}
				{if $profile->comingDueNotice==0}No{else}Yes{/if}
			{/if}
		</div>
	</div>

{/strip}