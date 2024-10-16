{strip}
	<form method="post" action="" id="overdriveCheckoutPromptsForm" class="form">
		<div>
			<input type="hidden" name="overdriveId" value="{$overDriveId}">
			<input type="hidden" name="formatType" id="formatType" value="magazine-overdrive">
			{if count($overDriveUsers) > 1} {* Linked Users contains the active user as well*}
				<div class="form-group">
					<label class="control-label" for="patronId">{translate text="Checkout to account"}: </label>
					<div class="controls">
						<select name="patronId" id="patronId" class="form-control" onchange="$('.lendingPeriods').hide();$('#lendingPeriod' + $(this).val()).show()">
							{foreach from=$overDriveUsers item=tmpUser}
								<option value="{$tmpUser->id}">{$tmpUser->displayName} - {$tmpUser->getHomeLibrarySystemName()}</option>
							{/foreach}
						</select>
					</div>
				</div>
			{else}
				{foreach from=$overDriveUsers item=tmpUser}{* hack to set the id *}
					<input type="hidden" id="patronId" value="{$tmpUser->id}">
				{/foreach}
			{/if}
			{if count($issues) > 1}
				<label class="control-label" for="issuesToCheckout">Please choose issue: </label>
				<select class="form-control" name="issueId" id="issuesToCheckout">
				{foreach from=$issues item=issue}
					<option value="{$issue->overdriveId}" {if $issue->overdriveId == $issueId}selected{/if}>{$issue->edition}</option>
				{/foreach}
				</select>
			{/if}

			{foreach from=$overDriveUsers item=tmpUser name=foo}
				{assign var="userId" value=$tmpUser->id}
				{* Have to assign the userid here to it's own variable because smarty can't parse correctly
			the mixing of arrays and object properties going on for the foreach loop below *}
				<div id="lendingPeriod{$userId}" class="lendingPeriods" {if $smarty.foreach.foo.index != 0} style="display: none"{*Hide all selects but the first one*}{/if}>
					{if $tmpUser->promptForOverDriveLendingPeriods && !empty($lendingPeriods.$userId)}
						<label class="control-label" for="lendingPeriodSelect{$userId}">{translate text="Lending Period"}: </label>
						<select name="lendingPeriod[{$userId}]" id="lendingPeriodSelect{$userId}" class="form-control">
							{foreach from=$lendingPeriods.$userId item=value}
								<option value="{$value}" {if $tmpUser->lendingPeriod == $value}selected="selected"{/if}>{$value} days</option>
							{/foreach}
						</select>
						<div class="form-group">
							<label for="useDefaultLendingPeriods{$userId}" class="control-label checkbox">
								<input type="checkbox" name="useDefaultLendingPeriods[{$userId}]" id="useDefaultLendingPeriods{$userId}"> Use My Default Lending Periods
							</label>
						</div>
						<div class="alert alert-info">
							<p>To use my default lending period settings for future checkouts, click <em>Use My Default Lending Periods</em> above.</p>
							<p>To change your default lending period settings or turn this prompt back on, visit the <a href="/MyAccount/Profile">Account Settings</a> page and adjust settings in the OverDrive Options section.</p>
						</div>
						{*
										{else}
											<span>Using default lending period</span>
						*}
					{/if}
				</div>
			{/foreach}
		</div>
	</form>

{/strip}