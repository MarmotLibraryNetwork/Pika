{strip}
	<form action="" method="post" class="form-horizontal">
		<input type="hidden" name="updateScope" value="overdrive">
	{if isset($overDriveSettings.checkoutLimit)}
		<div class="form-group">
			<div class="col-xs-4"><strong>Checkouts Limit</strong></div>
			<div class="col-xs-8">{$overDriveSettings.checkoutLimit}</div>
		</div>
	{/if}
	{if isset($overDriveSettings.holdLimit)}
		<div class="form-group">
			<div class="col-xs-4"><strong>Holds Limit</strong></div>
			<div class="col-xs-8">{$overDriveSettings.holdLimit}</div>
		</div>
	{/if}
		<div class="lead">OverDrive Hold Notification Email</div>
		<div class="alert alert-info">
		<p>
			This will be the default email address attached to your holds placed on OverDrive titles.
		</p>
		<p>
			If you turn off the <em><strong>{translate text='Set Notification Email While Placing Hold'}</strong></em> switch, we will use this email automatically and skip prompting you for an email address when placing your holds.
		</p>
		</div>
		<div class="form-group">
			<div class="col-xs-4"><label for="overDriveEmail" class="control-label">{translate text='Email Address'}:</label></div>
			<div class="col-xs-8">
					{if $edit == true}<input name="overDriveEmail" id="overDriveEmail" class="form-control" value='{$profile->overDriveEmail|escape}' size='50' maxlength='75'>{else}{$profile->overDriveEmail|escape}{/if}
			</div>
		</div>
		<div class="form-group">
			<div class="col-xs-4"><label for="promptForOverDriveEmail" class="control-label">{translate text='Set Notification Email While Placing Hold'}:</label></div>
			<div class="col-xs-8">
					{if $edit == true}
						<input type="checkbox" name="promptForOverDriveEmail" id="promptForOverDriveEmail" {if $profile->promptForOverDriveEmail==1}checked='checked'{/if} data-switch="">
					{else}
							{if $profile->promptForOverDriveEmail==0}No{else}Yes{/if}
					{/if}
			</div>
		</div>
			{if !empty($overDriveSettings.lendingPeriods)}
				<div class="lead">Lending periods</div>
				<div class="alert alert-info">
					<p>You may change your default lending period for each format. (Certain titles may have lending periods that can't be changed.)</p>
					<p>If you want to use these values every time you check out a title and skip being prompted to set the lending period at check out, please turn off <em><strong>{translate text='Set Lending Period During Checkout'}</strong></em></p>
				</div>
					{foreach from=$overDriveSettings.lendingPeriods item=lendingOption key="formatType"}
						<div class="form-group">
							<div class="col-xs-4"><label class="control-label">{$formatType}:</label></div>
							<div class="col-xs-8">
								<div class="btn-group btn-group-sm" data-toggle="buttons">
										{foreach from=$lendingOption->options item=option}
												{if $edit}
													<label for="{$formatType}_{$option}" class="btn btn-sm btn-default {if $lendingOption->lendingPeriod == $option}active{/if}"><input type="radio" name="lendingPeriods[{$formatType}]" value="{$option}" id="{$formatType}_{$option}" {if $lendingOption->lendingPeriod  == $option}checked="checked"{/if} class="form-control">&nbsp;{$option}</label>
												{elseif $lendingOption->lendingPeriod}
														{$option.name}
												{/if}
										{/foreach}
								</div>
								&nbsp;days
							</div>
						</div>
					{/foreach}
				<div class="form-group">
					<div class="col-xs-4"><label for="promptForOverDriveLendingPeriods" class="control-label">{translate text='Set Lending Period During Checkout'}:</label></div>
					<div class="col-xs-8">
							{if $edit == true}
								<input type="checkbox" name="promptForOverDriveLendingPeriods" id="promptForOverDriveLendingPeriods" {if $profile->promptForOverDriveLendingPeriods==1}checked='checked'{/if} data-switch="">
							{else}
									{if $profile->promptForOverDriveLendingPeriods==0}No{else}Yes{/if}
							{/if}
					</div>
				</div>

      {/if}

			{if !$offline && $edit == true}
				<div class="form-group">
					<div class="col-xs-8 col-xs-offset-4">
						<input type="submit" value="Update OverDrive Options" name="updateOverDrive" class="btn btn-sm btn-primary">
					</div>
				</div>
			{/if}

			{if !empty($overDrivePreferencesNotice)}
				<p class="help-block alert alert-info">
						{$overDrivePreferencesNotice}
				</p>
			{/if}

	</form>
{/strip}