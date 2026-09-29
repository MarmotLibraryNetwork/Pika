{strip}
	<div class="row mb-3">
		<div class="col-sm-4"><strong>{translate text='Receive notices by'}:</strong></div>
		<div class="col-sm-8">
			{if !$offline && $canUpdateContactInfo == true}
				<div class="btn-group btn-group-sm">
					{if $treatPrintNoticesAsPhoneNotices}
						{* Tell the User the notice is Phone even though in the ILS it will be print *}
						{* MDN 2/24/2016 - If the user changes their notice preference, make it phone to be more accurate, but show as selected if either print or mail is shown *}
						<input type="radio" class="btn-check" value="p" id="sendEmail" name="notices" autocomplete="off"{if $profile->notices == 'a' || $profile->notices == 'p'} checked="checked"{/if}><label for="sendEmail" class="btn btn-sm btn-outline-secondary">Telephone</label>
					{else}
						<input type="radio" class="btn-check" value="a" id="noticesMail" name="notices" autocomplete="off"{if $profile->notices == 'a'} checked="checked"{/if}><label for="noticesMail" class="btn btn-sm btn-outline-secondary">Postal Mail</label>
						<input type="radio" class="btn-check" value="p" id="noticesTel" name="notices" autocomplete="off"{if $profile->notices == 'p'} checked="checked"{/if}><label for="noticesTel" class="btn btn-sm btn-outline-secondary">Telephone</label>
					{/if}
					<input type="radio" class="btn-check" value="z" id="noticesEmail" name="notices" autocomplete="off"{if $profile->notices == 'z'} checked="checked"{/if}><label for="noticesEmail" class="btn btn-sm btn-outline-secondary">Email</label>
					<input type="radio" class="btn-check" value="t" id="noticesText" name="notices" autocomplete="off"{if $profile->notices == 't'} checked="checked"{/if}><label for="noticesText" class="btn btn-sm btn-outline-secondary">Text</label>
				</div>
			{else}
				{$profile->noticePreferenceLabel|escape}
			{/if}
		</div>
	</div>
{/strip}