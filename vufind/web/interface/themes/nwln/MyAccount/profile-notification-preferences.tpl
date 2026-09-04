{strip}
	<div class="row mb-3">
		<div class="col-sm-4"><strong>{translate text='Receive notices by'}:</strong></div>
		<div class="col-sm-8">
			{if !$offline && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm">
					<input type="radio" class="btn-check" value="p" id="noticesTel" name="notices" autocomplete="off"{if $profile->notices == 'p'} checked="checked"{/if}><label for="noticesTel" class="btn btn-sm btn-outline-secondary">Telephone</label>
					<input type="radio" class="btn-check" value="t" id="noticesText" name="notices" autocomplete="off"{if $profile->notices == 't'} checked="checked"{/if}><label for="noticesText" class="btn btn-sm btn-outline-secondary">Text</label>
					<input type="radio" class="btn-check" value="z" id="noticesEmail" name="notices" autocomplete="off"{if $profile->notices == 'z'} checked="checked"{/if}><label for="noticesEmail" class="btn btn-sm btn-outline-secondary">Email</label>
					<input type="radio" class="btn-check" value="-" id="noticesNone" name="notices" autocomplete="off"{if $profile->notices == '-'} checked="checked"{/if}><label for="noticesNone" class="btn btn-sm btn-outline-secondary">No Preference</label>
				</div>
			{else}
				{$profile->noticePreferenceLabel|escape}
			{/if}
		</div>
	</div>
	{* Northern Waters uses the phone number type p (which we designate as the work phone number)
	as the text messaging number.
	It will be important to keep the setting $showWorkPhoneInProfile off so that this field isn't
	displayed twice in the form.
	We will use the language translation for the label so that if the $showWorkPhoneInProfile is
	turned on, it is more evident to Admins what is going on here "under the hood"
	 *}
	<div class="row mb-3">
		<div class="col-sm-4"><label for="workPhone">{translate text='Work Phone Number'}:</label></div>
		<div class="col-sm-8">{if !$offline && $canUpdateContactInfo && $ils != 'Horizon'}<input name="workPhone" id="workPhone" value="{$profile->workPhone|escape}" size="50" maxlength="75" class="form-control simplePhoneFormat">
				<p class='alert alert-warning'><strong>(Format: xxx-xxx-xxxx) &nbsp; Be sure to include the dashes.</strong></p>
			{else}{$profile->workPhone|escape}{/if}</div>
	</div>
	<script>
		jQuery.validator.addMethod("simplePhoneFormat",
		{literal}
			function (value, element){
				return this.optional(element) || /^\d{3}-\d{3}-\d{4}$/.test(value);
			}, "Format: xxx-xxx-xxxx");
		{/literal}
		$("#contactUpdateForm").validate();
	</script>
{/strip}