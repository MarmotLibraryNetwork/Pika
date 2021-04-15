{strip}
	<div class="form-group">
		<div class="col-xs-4"><strong>{translate text='Receive notices by'}:</strong></div>
		<div class="col-xs-8">
			{if $edit == true && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm" data-toggle="buttons">
					{if $treatPrintNoticesAsPhoneNotices}
						{* Tell the User the notice is Phone even though in the ILS it will be print *}
						{* MDN 2/24/2016 - If the user changes their notice preference, make it phone to be more accurate, but show as selected if either print or mail is shown *}
						<label for="sendEmail" class="btn btn-sm btn-default {if $profile->notices == 'a'}active{/if}"><input type="radio" value="p" id="sendEmail" name="notices" {if $profile->notices == 'a' || $profile->notices == 'p'}checked="checked"{/if}> Telephone</label>
					{else}
						<label for="noticesMail" class="btn btn-sm btn-default {if $profile->notices == 'a'}active{/if}"><input type="radio" value="a" id="noticesMail" name="notices" {if $profile->notices == 'a'}checked="checked"{/if}> Postal Mail</label>
						<label for="noticesTel" class="btn btn-sm btn-default {if $profile->notices == 'p'}active{/if}"><input type="radio" value="p" id="noticesTel" name="notices" {if $profile->notices == 'p'}checked="checked"{/if}> Telephone</label>
					{/if}
					<label for="noticesEmail" class="btn btn-sm btn-default {if $profile->notices == 'z'}active{/if}"><input type="radio" value="z" id="noticesEmail" name="notices" {if $profile->notices == 'z'}checked="checked"{/if}> Email</label>
					<label for="noticesNone" class="btn btn-sm btn-default {if $profile->notices == '-'}active{/if}"><input type="radio" value="-" id="noticesNone" name="notices" {if $profile->notices == '-'}checked="checked"{/if}> No Preference</label>
				</div>
			{else}
				{$profile->noticePreferenceLabel|escape}
			{/if}
		</div>
	</div>
{/strip}