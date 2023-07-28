{strip}
<form method="post" action="" id="overDriveHoldPromptsForm" class="form">
	<div>
		<input type="hidden" name="overdriveId" value="{$overDriveId}">
		{if count($overDriveUsers) > 1} {* Linked Users contains the active user as well*}
			<div class="form-group">
				<label class="control-label" for="patronId">{translate text="Place hold for account"}: </label>
				<div class="controls">
					<select name="patronId" id="patronId" class="form-control">
						{foreach from=$overDriveUsers item=tmpUser}
							<option value="{$tmpUser->id}" {if $location->selected == "selected"}selected="selected"{/if}>{$tmpUser->displayName} - {$tmpUser->getHomeLibrarySystemName()}</option>
						{/foreach}
					</select>
				</div>
			</div>
		{else}
			<input type="hidden" name="patronId" id="patronId" value="{$patronId}">
		{/if}

	{include file="OverDrive/ajax-overdrive-hold-notification-email.tpl"}
	</div>
</form>
{/strip}