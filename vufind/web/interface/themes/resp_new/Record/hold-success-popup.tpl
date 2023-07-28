{strip}
	{* $profile is set in AJAX.php as the account the hold was placed with. *}
	<div class="content">
		{if $success}
			<p class="alert alert-success">{$message}</p>
			{if $showDetailedHoldNoticeInformation}
				<div class="alert alert-info">
					{translate text='Once the title arrives at your library'} you will
					{if $profile->noticePreferenceLabel eq 'Mail' && !$treatPrintNoticesAsPhoneNotices} be mailed a notification to :
						<blockquote class="alert-warning">
							{if $canUpdate}<a href="/MyAccount/Profile?patronId={$profile->id}"><span class="glyphicon glyphicon-pencil"></span> {/if}
								{$profile->address1} {$profile->address2}
							{if $canUpdate}</a>{/if}
						</blockquote>
					{elseif $profile->noticePreferenceLabel eq 'Telephone' || ($profile->noticePreferenceLabel eq 'Mail' && $treatPrintNoticesAsPhoneNotices)} be called at :
						<blockquote class="alert-warning">
							{if $canUpdate}<a href="/MyAccount/Profile?patronId={$profile->id}"><span class="glyphicon glyphicon-pencil"></span> {/if}
							{$profile->phone}
							{if $canUpdate}</a>{/if}
						</blockquote>
					{elseif $profile->noticePreferenceLabel eq 'E-mail'} be emailed a notification at :
						<blockquote class="alert-warning">
							{if $canUpdate}<a href="/MyAccount/Profile?patronId={$profile->id}"><span class="glyphicon glyphicon-pencil"></span> {/if}
							{$profile->email}
							{if $canUpdate}</a>{/if}
						</blockquote>
					{else} receive a notification informing you that the title is ready for you to pick up.
						{if $canChangeNoticePreference}
							<br><br>
							<a href="/MyAccount/Profile?patronId={$profile->id}"><span class="glyphicon glyphicon-pencil"></span>Click if you would like to set your notification preferences.</a>
						{/if}
					{/if}
				</div>
			{/if}
		{else}
			<p class="alert alert-danger">{$message}</p>
		{/if}
	</div>
{/strip}