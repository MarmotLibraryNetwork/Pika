{strip}

	{*******************************************
			ATENTION DEVELOPERS!!!

	 NOTE :  There is more than one  *profile.tpl*  template file.
			Update all of them with your changes.

	polaris/MyAccount/profile.tpl
	********************************************}
	<div id="main-content">
		{if $loggedIn}
			{include file="MyAccount/patronWebNotes.tpl"}

			{* Alternate Mobile MyAccount Menu *}
			{include file="MyAccount/mobilePageHeader.tpl"}
			<span class="availableHoldsNoticePlaceHolder"></span>
			<h1 role="heading" aria-level="1" class="h2">{translate text='Account Settings'}</h1>
			{if $offline}
				<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable
					to retrieve information about your {translate text='Account Settings'|lower} at this time.
				</div>
			{else}

				{if $profileUpdateErrors}
					{foreach from=$profileUpdateErrors item=errorMsg}
						{if strpos($errorMsg, 'success')}
							<div class="alert alert-success">{$errorMsg}</div>
						{else}
							<div class="alert alert-danger">{$errorMsg}</div>
						{/if}
					{/foreach}
				{/if}

				{include file="MyAccount/switch-linked-user-form.tpl" label="View Account Settings for" actionPath="/MyAccount/Profile"}
				<br>
				<div class="panel-group" id="account-settings-accordion">
					<div class="panel active">
						<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#basicPanel">
							<div class="panel-heading">
								<h2 class="panel-title">
									Basic Information
								</h2>
							</div>
						</a>
						<div id="basicPanel" class="panel-collapse collapse in">
							<div class="panel-body">
								<form action="" method="post" class="form-horizontal">
									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Full Name'}:</strong></div>
										<div class="col-xs-8">{$profile->fullname|escape}</div>
									</div>
									{if !$offline && !is_null($profile->legalFullName)}
										<div class="form-group">
											<div class="col-xs-4"><strong>{translate text='Full Legal Name'}:</strong></div>
											<div class="col-xs-8">{$profile->legalFullName|escape}</div>
										</div>
									{/if}
									{if !$offline}
										{if $barcodePin}
											<div class="form-group">
												<div class="col-xs-4">
													<strong>{translate text='Library Card Number'}:</strong>
												</div>
												<div class="col-xs-8">{$profile->barcode|escape}</div>
											</div>
										{/if}
										<div class="form-group">
											<div class="col-xs-4">
												<strong>{translate text='Expiration Date'}:</strong>
											</div>
											<div class="col-xs-8">{$profile->expires|escape}</div>
										</div>
									{/if}
									<div class="form-group">
										<div class="col-xs-4"><strong>{translate text='Home Library'}:</strong></div>
										<div class="col-xs-8">{$profile->homeLocation|escape}</div>
									</div>
								</form>
							</div>
						</div>
					</div>

					<div class="panel active">
						<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#contactPanel">
							<div class="panel-heading">
								<h2 class="panel-title">
									Contact Information
								</h2>
							</div>
						</a>
						<div id="contactPanel" class="panel-collapse collapse in">
							<div class="panel-body">
								{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
								<form action="" method="post" class="form-horizontal" id="contactUpdateForm">
									<input type="hidden" name="updateScope" value="contact">

									{if !$offline}
										<div class="form-group">
											<div class="col-xs-4">
												<label for="address1">{translate text='Address'}:</label>
											</div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo && $canUpdateAddress}
													<input name="address1" id="address1"
													       value='{$profile->address1|escape}' size="50" maxlength="75"
													       class="form-control required" aria-required="true">
												{elseif !$offline && $millenniumNoAddress}
													<input name="address1" id="address1"
													       value='{$profile->address1|escape}' type="hidden">
													{if $profile->careOf}{$profile->careOf|escape}<br>{/if}
													{$profile->address1|escape}
												{else}
													{$profile->address1|escape}
												{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4">
												<label for="address2">{translate text='Address 2'}:</label>
											</div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo && $canUpdateAddress}
													<input name="address2" id="address2"
													       value='{$profile->address2|escape}' size="50" maxlength="75"
													       class="form-control required" aria-required="true">
												{elseif !$offline && $millenniumNoAddress}
													<input name="address2" id="address2"
													       value='{$profile->address2|escape}' type="hidden">
													{if $profile->careOf}{$profile->careOf|escape}<br>{/if}
													{$profile->address2|escape}
												{else}
													{$profile->address2|escape}
												{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4"><label for="city">{translate text='City'}:</label>
											</div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo && $canUpdateAddress}
													<input name="city" id="city" value="{$profile->city|escape}"
													       size="50" maxlength="75" class="form-control required">
												{elseif !$offline && $millenniumNoAddress}
													<input name="city" id="city" value="{$profile->city|escape}"
													       type="hidden">
													{$profile->city|escape}
												{else}{$profile->city|escape}{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4"><label for="state">{translate text='State'}:</label>
											</div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo && $canUpdateAddress}
													<input name='state' id="state" value="{$profile->state|escape}"
													       size="50" maxlength="75" class="form-control required">
												{elseif !$offline && $millenniumNoAddress}
													<input name="state" id="state" value="{$profile->state|escape}"
													       type="hidden">
													{$profile->state|escape}
												{else}{$profile->state|escape}{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4"><label for="zip">{translate text='Zip'}:</label></div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo && $canUpdateAddress}
													<input name="zip" id="zip" value="{$profile->zip|escape}" size="50"
													       maxlength="75" class="form-control required">
												{elseif !$offline && $millenniumNoAddress}
													<input name="zip" id="zip" value="{$profile->zip|escape}" type="hidden">
													{$profile->zip|escape}
												{else}{$profile->zip|escape}{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4">
												<label for="phone">{translate text='Primary Phone Number'}:</label>
											</div>
											<div class="col-xs-8">
												{if $canUpdateContactInfo}
													<input type="tel" name="phone" id="phone"
													       value="{$profile->phone|escape}" size="50" maxlength="75"
													       class="form-control">
												{else}
													{$profile->phone|escape}
												{/if}
											</div>
										</div>
										<div class="form-group">
											<div class="col-xs-4">
												<label for="Phone1CarrierID">{translate text='Primary Phone Carrier'}:</label>
											</div>
											{if $canUpdateContactInfo}
												<div class="col-xs-8">
													<select name="Phone1CarrierID" id="Phone1CarrierID" class="form-control">
														{if count($phoneCarriers) > 0}
															{foreach from=$phoneCarriers key=k item=carrier}
																<option value="{$k}"{if $k == $profile->phone_carrier_id} selected="selected"{/if}>{$carrier}</option>
															{/foreach}
														{else}
															<option>&nbsp</option>
														{/if}
													</select>
												</div>
											{/if}
										</div>
										{if $showPhone2}
											<div class="form-group">
												<div class="col-xs-4">
													<label for="phone2">{translate text='Secondary Phone Number'}:</label>
												</div>
												<div class="col-xs-8">{if $canUpdateContactInfo}
														<input name="phone2" id="phone2"
														       value="{$profile->phone2|escape}" size="50"
														       maxlength="75"
														       class="form-control">{else}{$profile->phone2|escape}{/if}
												</div>
											</div>
											<div class="form-group">
												<div class="col-xs-4">
													<label for="Phone2">{translate text='Secondary Phone Carrier'}:</label>
												</div>
												{if $canUpdateContactInfo}
													<div class="col-xs-8">
														<select name="Phone2CarrierID" id="Phone2CarrierID" class="form-control">
															{if count($phoneCarriers) > 0}
																{foreach from=$phoneCarriers key=k item=carrier}
																	<option value="{$k}"{if $k == $profile->phone2_carrier_id} selected="selected"{/if}>{$carrier}</option>
																{/foreach}
															{else}
																<option>&nbsp</option>
															{/if}
														</select>
													</div>
												{/if}
											</div>
										{/if}

										{if $showPhone3}
											<div class="form-group">
												<div class="col-xs-4">
													<label for="phone3">{translate text='Alternate Phone Number'}:</label>
												</div>
												<div class="col-xs-8">{if $canUpdateContactInfo}
														<input name="phone3" id="phone3"
														       value="{$profile->phone3|escape}" size="50"
														       maxlength="75"
														       class="form-control">{else}{$profile->phone3|escape}{/if}
												</div>
											</div>
											<div class="form-group">
												<div class="col-xs-4">
													<label for="Phone3CarrierID">{translate text='Alternate Phone Carrier'}:</label>
												</div>
												{if $canUpdateContactInfo}
													<div class="col-xs-8">
														<select name="Phone3CarrierID" id="Phone3CarrierID" class="form-control">
															{if count($phoneCarriers) > 0}
																{foreach from=$phoneCarriers key=k item=carrier}
																	<option value="{$k}"{if $k == $profile->phone3_carrier_id} selected="selected"{/if}>{$carrier}</option>
																{/foreach}
															{else}
																<option>&nbsp</option>
															{/if}
														</select>
													</div>
												{/if}
											</div>
										{/if}
									{/if}
									<div class="form-group">
										<div class="col-xs-4"><label for="email">{translate text='E-mail'}:</label>
										</div>
										<div class="col-xs-8">
											{if !empty($linkedUsers) && count($linkedUsers) > 1 && $selectedUser != $activeUserId}
												{*Security: Prevent changing email, username, or password for linked accounts. See D-4031 *}
												{$profile->email|escape}
											{else}
												{if !$offline && $canUpdateContactInfo == true}
													<input type="text" name="email" id="email"
													       value="{$profile->email|escape}" size="50" maxlength="75"
													       class="form-control multiemail">
													{* Multiemail class is for form validation; type has to be text for multiemail validation to work correctly *}
												{else}{$profile->email|escape}{/if}
											{/if}
										</div>
									</div>
									{if $showPickupLocationInProfile}
										<div class="form-group">
											<div class="col-xs-4">
												<label for="pickupLocation" class="">{translate text='Pickup Location'}:</label>
											</div>
											<div class="col-xs-8">
												{if !$offline && $canUpdateContactInfo == true}
													<select name="pickupLocation" id="pickupLocation" class="form-control">
														{if count($pickupLocations) > 0}
															{foreach from=$pickupLocations item=location}
																<option value="{$location->ilsLocationId}"
																        {if $location->ilsLocationId|escape == $profile->preferredPickupLocationId|escape}selected="selected"{/if}>{$location->displayName}</option>
															{/foreach}
														{else}
															<option>placeholder</option>
														{/if}
													</select>
												{else}
													{$profile->homeLocation|escape}
												{/if}
											</div>
										</div>
									{/if}

									{if !$offline && $canUpdateContactInfo}
										<div class="form-group">
											<div class="col-xs-8 col-xs-offset-4">
												<input type="submit" value="Update Contact Information"
												       name="updateContactInfo" class="btn btn-sm btn-primary">
											</div>
										</div>
									{/if}
								</form>
							</div>
						</div>
					</div>

					{if $showNoticeTypeInProfile}
						{include file="MyAccount/notifications.tpl"}
					{/if}

					{if !empty($linkedUsers) && count($linkedUsers) > 1 && $selectedUser != $activeUserId}
						{*Security: Prevent changing email, username, or password for linked accounts. See D-4031 *}
					{else}
						{include file="MyAccount/pinReset.tpl"}
						{* end of update pin section *}
					{/if}
					{* end of linked accounts checked for update pin section *}

					{include file="MyAccount/userName.tpl"}

					{*OverDrive Options*}
					{if $profile->isValidForOverDrive()}
						<div class="panel active">
							<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#overdrivePanel">
								<div class="panel-heading">
									<h2 class="panel-title">
										OverDrive Options
									</h2>
								</div>
							</a>
							<div id="overdrivePanel" class="panel-collapse collapse in">
								<div class="panel-body">
									{include file="MyAccount/profile-overdrive-options.tpl"}
								</div>
							</div>
						</div>
					{/if}

					{*Hoopla Options*}
					{if $profile->isValidForHoopla()}
						<div class="panel active">
							<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#hooplaPanel">
								<div class="panel-heading">
									<h2 class="panel-title">
										Hoopla Options
									</h2>
								</div>
							</a>
							<div id="hooplaPanel" class="panel-collapse collapse in">
								<div class="panel-body">
									{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
									<form action="" method="post" class="form-horizontal">
										<input type="hidden" name="updateScope" value="hoopla">
										<div class="form-group">
											<div class="col-xs-4"><label for="hooplaCheckOutConfirmation"
											                             class="control-label">{translate text='Ask for confirmation before checking out from Hoopla'}
													:</label></div>
											<div class="col-xs-8">
												{if !$offline}
													<input type="checkbox" name="hooplaCheckOutConfirmation"
													       id="hooplaCheckOutConfirmation"
													       {if $profile->hooplaCheckOutConfirmation==1}checked='checked'{/if}
													       data-switch="">
												{else}
													{if $profile->hooplaCheckOutConfirmation==0}No{else}Yes{/if}
												{/if}
											</div>
										</div>
										{if !$offline}
											<div class="form-group">
												<div class="col-xs-8 col-xs-offset-4">
													<input type="submit" value="Update Hoopla Options"
													       name="updateHoopla" class="btn btn-sm btn-primary">
												</div>
											</div>
										{/if}
									</form>
								</div>
							</div>
						</div>
					{/if}

					{*User Preference Options*}
					{if $showAlternateLibraryOptions || $userIsStaff || ($showRatings && $showComments)}
						<div class="panel active">
							<a data-toggle="collapse" data-parent="#account-settings-accordion"
							   href="#userPreferencePanel">
								<div class="panel-heading">
									<h2 class="panel-title">
										My Preferences
									</h2>
								</div>
							</a>
							<div id="userPreferencePanel" class="panel-collapse collapse in">
								<div class="panel-body">
									{* Empty action attribute uses the page loaded. this keeps the selected user patronId in the parameters passed back to server *}
									<form action="" method="post" class="form-horizontal">
										<input type="hidden" name="updateScope" value="userPreference">

										{if $showAlternateLibraryOptions}
											<div class="form-group">
												<div class="col-xs-4"><label for="myLocation1"
												                             class="control-label">{translate text='My First Alternate Library'}
														:</label></div>
												<div class="col-xs-8">
													{if !$offline}
														{html_options name="myLocation1" id="myLocation1" class="form-control" options=$locationList selected=$profile->myLocation1Id}
													{else}
														{$profile->myLocation1|escape}
													{/if}
												</div>
											</div>
											<div class="form-group">
												<div class="col-xs-4"><label for="myLocation2"
												                             class="control-label">{translate text='My Second Alternate Library'}
														:</label></div>
												<div class="col-xs-8">{if !$offline}{html_options name="myLocation2" id="myLocation2" class="form-control" options=$locationList selected=$profile->myLocation2Id}{else}{$profile->myLocation2|escape}{/if}</div>
											</div>
										{/if}

										{if $showRatings && $showComments}
											<div class="form-group">
												<div class="col-xs-4"><label for="noPromptForUserReviews"
												                             class="control-label">{translate text='Do not prompt me for reviews after rating titles'}
														:</label></div>
												<div class="col-xs-8">
													{if !$offline}
														<input type="checkbox" name="noPromptForUserReviews"
														       id="noPromptForUserReviews"
														       {if $profile->noPromptForUserReviews==1}checked='checked'{/if}
														       data-switch="">
													{else}
														{if $profile->noPromptForUserReviews==0}No{else}Yes{/if}
													{/if}
													<p class="help-block alert alert-warning">When you rate an item by
														clicking on the stars, you will be asked to review that item
														also. Setting this option to <strong>&quot;on&QUOT;</strong>
														lets us know you don't want to give reviews after you have rated
														an item by clicking its stars.</p>
												</div>
											</div>
										{/if}

										{if !$offline}
											<div class="form-group">
												<div class="col-xs-8 col-xs-offset-4">
													<input type="submit" value="Update My Preferences"
													       name="updateMyPreferences" class="btn btn-sm btn-primary">
												</div>
											</div>
										{/if}
									</form>
								</div>
							</div>
						</div>
					{/if}

					{if $allowAccountLinking}
						<div class="panel active">
							<a data-toggle="collapse" data-parent="#account-settings-accordion"
							   href="#linkedAccountPanel">
								<div class="panel-heading">
									<h2 class="panel-title">
										Linked Accounts
									</h2>
								</div>
							</a>
							<div id="linkedAccountPanel" class="panel-collapse collapse in">
								<div class="panel-body">
									<div class="alert alert-info">
										<p>
											Linked accounts allow you to easily maintain multiple accounts for the
											library so you can see all of your information in one place. Information
											from linked accounts will appear when you view your checkouts, holds, etc. in
											the main account.
										</p>
										<p>
											For more information about Linked Accounts, see the <a
															href="https://marmot-support.atlassian.net/l/cp/GiuLxH78">online
												documentation</a>.
										</p>
									</div>
									<div class="lead">Additional accounts to manage</div>
									<p>The following accounts can be managed from this account.</p>
									<ul>
										{foreach from=$profile->linkedUsers item=tmpUser}  {* Show linking for the account currently chosen for display in account settings *}
											<li>{$tmpUser->getNameAndLibraryLabel()}
												<button class="btn btn-xs btn-warning"
												        onclick="Pika.Account.removeLinkedUser({$tmpUser->id});">Remove
												</button>
											</li>
											{foreachelse}
											<li>None</li>
										{/foreach}
									</ul>
									{if $user->id == $profile->id}{* Only allow account adding for the actual account user is logged in with *}
										<button class="btn btn-primary btn-xs" onclick="Pika.Account.addAccountLink()">
											Add an Account
										</button>
									{else}
										<p>Log into this account to add other accounts to it.</p>
									{/if}
									<div class="lead">Other accounts that can view this account</div>
									<p>The following accounts can view checkout and hold information from this account.
										If someone is viewing your account that you do not want to have access, please
										contact library staff.</p>
									<ul>
										{foreach from=$profile->getViewers() item=tmpUser}
											<li>{$tmpUser->getNameAndLibraryLabel()}
												<button class="btn btn-xs btn-warning"
												        onclick="Pika.Account.removeViewer({$tmpUser->id});">Remove
												</button>
											</li>
											{foreachelse}
											<li>None</li>
										{/foreach}
									</ul>
								</div>
							</div>
						</div>
					{/if}

					{* Display user roles if the user has any roles*}
					{if $userIsStaff || count($profile->roles) > 0}
						<div class="panel active">
							<a data-toggle="collapse" data-parent="#account-settings-accordion" href="#rolesPanel">
								<div class="panel-heading">
									<h2 class="panel-title">
										Staff Settings
									</h2>
								</div>
							</a>
							<div id="rolesPanel" class="panel-collapse collapse in">
								<div class="panel-body">
									{include file="MyAccount/profile-staff-settings.tpl"}
								</div>
							</div>
						</div>
					{/if}
				</div>
			{/if}
		{else}
			{include file="MyAccount/loginRequired.tpl"}
		{/if}
	</div>
{/strip}
