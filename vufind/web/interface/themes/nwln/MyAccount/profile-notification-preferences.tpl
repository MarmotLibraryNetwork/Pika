{strip}
	<div class="form-group">
		<div class="col-xs-4"><strong>{translate text='Receive notices by'}:</strong></div>
		<div class="col-xs-8">
			{if !$offline && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm">
					<label for="noticesTel" class="btn btn-sm btn-default{* {if $profile->notices == 'p'}active{/if}*}"><input type="radio" value="p" id="noticesTel" name="notices" {if $profile->notices == 'p'}checked="checked"{/if}> Telephone</label>
					<label for="noticesText" class="btn btn-sm btn-default{* {if $profile->notices == 't'}active{/if}*}"><input type="radio" value="t" id="noticesText" name="notices" {if $profile->notices == 't'}checked="checked"{/if}> Text</label>
					<label for="noticesEmail" class="btn btn-sm btn-default{* {if $profile->notices == 'z'}active{/if}*}"><input type="radio" value="z" id="noticesEmail" name="notices" {if $profile->notices == 'z'}checked="checked"{/if}> Email</label>
					<label for="noticesNone" class="btn btn-sm btn-default{* {if $profile->notices == '-'}active{/if}*}"><input type="radio" value="-" id="noticesNone" name="notices" {if $profile->notices == '-'}checked="checked"{/if}> No Preference</label>
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
	<div class="form-group">
		<div class="col-xs-4"><label for="workPhone">{translate text='Work Phone Number'}:</label></div>
		<div class="col-xs-8">{if !$offline && $canUpdateContactInfo && $ils != 'Horizon'}<input name="workPhone" id="workPhone" value="{$profile->workPhone|escape}" size="50" maxlength="75" class="form-control simplePhoneFormat">
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