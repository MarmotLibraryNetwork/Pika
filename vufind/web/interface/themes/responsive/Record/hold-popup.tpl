{strip}
<div id="page-content" class="content">

	{foreach from=$maxHolds item="maxHold"}
		<blockquote class="alert-warning">{$maxHold}</blockquote>
	{/foreach}

	<p class="alert alert-info">
			{/strip}
		Holds allow you to request that a title be delivered to the library.
			{if $showDetailedHoldNoticeInformation}
				Once the title arrives at the library you will
					{if $profile->noticePreferenceLabel eq 'Mail' && !$treatPrintNoticesAsPhoneNotices}
						be mailed a notification
					{elseif $profile->noticePreferenceLabel eq 'Telephone' || ($profile->noticePreferenceLabel eq 'Mail' && $treatPrintNoticesAsPhoneNotices)}
						receive a phone call
					{elseif $profile->noticePreferenceLabel eq 'E-mail'}
						be emailed a notification
					{else}
						receive a notification
					{/if}
				informing you that the title is ready for you.
			{else}
				Once the title arrives at the library you will receive a notification informing you that the title is ready for you.
			{/if}
		You will then have {translate text='Hold Pickup Period'} to pick up the title from the library.
			{strip}
	</p>

	{if $hasHomePickupItems}
		<p class="alert alert-warning">
			This title includes Home Pickup items. <strong>Holds on Home Pickup items can only be picked up at the library location that owns them.</strong> The pickup options below will reflect that.
		</p>
	{/if}
	<form name="placeHoldForm" id="placeHoldForm" method="post" class="form">
		<input type="hidden" name="id" id="id" value="{$id}">
		<input type="hidden" name="recordSource" id="recordSource" value="{$recordSource}">
		<input type="hidden" name="module" id="module" value="{$activeRecordProfileModule}">
		{if $volume}
			<input type="hidden" name="volume" id="volume" value="{$volume}">
		{/if}
		{if $hasHomePickupItems}
			<input type="hidden" name="hasHomePickupItems" id="hasHomePickupItems" value=1>
		{/if}
		<fieldset>

			{* Responsive theme enforces that the user is always logged in before getting here*}
			<div id="holdOptions">
				<div id="pickupLocationOptions" class="form-group">
					<label class="control-label" for="campus">{translate text="I want to pick this up at"}: </label>
					<div class="controls">
						<select name="campus" id="campus" class="form-control">
							{if count($pickupLocations) > 0}
								{foreach from=$pickupLocations item=location}
									{if is_string($location)}
										<option value="undefined">{$location}</option>
									{else}
										<option value="{$location->code}"{if $location->selected == "selected"} selected="selected"{/if}
										        data-users="[{$location->pickupUsers}]">{$location->displayName}</option>
									{/if}
								{/foreach}
							{else}
								<option>placeholder</option>
							{/if}
						</select>
					</div>
				</div>
					{*<div id="userOption" class="form-group"{*if count($pickupLocations[0]->pickupUsers) < 2} style="display: none"{/if* }>{* display if the first location will need a user selected*}
					<div id="userOption" class="form-group"{if !$multipleUsers} style="display: none"{/if}>{* display if there are multiple accounts *}
						<label for="user" class="control-label">{translate text="Place hold for the chosen location using account"}: </label>
						<div class="controls">
							<select name="user" id="user" class="form-control">
								{* Built by jQuery below *}
							</select>
						</div>
					</div>
					<script>
						{literal}
						$(function(){
							var userNames = {
							{/literal}
							{$activeUserId}: "{$userDisplayName|escape:javascript} - {$user->getHomeLibrarySystemName()}",
							{assign var="linkedUsers" value=$user->getLinkedUsers()}
							{foreach from="$linkedUsers" item="tron"}
								{$tron->id}: "{$tron->displayName|escape:javascript} - {$tron->getHomeLibrarySystemName()}",
							{/foreach}
							{literal}
								};
							$('#campus').change(function(){
								var users = $('option:selected', this).data('users'),
										options = '';
								if (typeof(users) !== "undefined") {
									$.each(users, function (indexIgnored, userId) {
										options += '<option value="' + userId + '">' + userNames[userId] + '</option>';
									});
								}
								$('#userOption select').html(options);
							}).change(); /* trigger on initial load */
						});
						{/literal}
					</script>
          {if $allowStaffPlacedHoldRequest}
						<div id="staffPlacedHolds" class="form-group has-success">
							<label for="patronBarcode" class="control-label">Or place hold for patron with {$patronBarcodeLabel} :</label>
							<div class="controls">
								<input type="text" name="patronBarcode" id="patronBarcode" class="form-control" size="10">
							</div>
						</div>
          {/if}
          {if $showHoldCancelDate}
					<div id="cancelHoldDate" class="form-group">
						<label class="control-label" for="canceldate">{translate text="Automatically cancel this hold if not filled by"}:</label>
						<div class="input-group input-append date controls" id="cancelDatePicker">
							{* data-provide attribute loads the datepicker through bootstrap data api *}
							{* start date sets minimum, end date sets maximum, date sets initial value: days from today, eg +8d is 8 days from now. *}
							<input type="text" name="canceldate" id="canceldate" placeholder="mm/dd/yyyy" class="form-control" size="10"
							       data-provide="datepicker" data-date-format="mm/dd/yyyy" data-date-start-date="0d" data-date-end-date="+1y">
							<span class="input-group-addon"><span class="glyphicon glyphicon-calendar" onclick="$('#canceldate').focus().datepicker('show')" aria-hidden="true"></span></span>
						</div>

							<p><i>{translate text="automatic_cancellation_notice"}</i></p>
							{foreach from=$autoCancels item="notice"}
									<p><i>{$notice}</i></p>
							{/foreach}

					</div>
				{/if}
				{if !empty($holdDisclaimers)}
					{foreach from=$holdDisclaimers item=holdDisclaimer key=library}
						<div class="holdDisclaimer alert alert-warning">
							{if count($holdDisclaimers) > 1}<div class="bold">{$library}</div>{/if}
							{$holdDisclaimer}
						</div>
					{/foreach}
				{/if}
				<br>
				<div class="form-group">
					<label for="autologout" class="checkbox"><input type="checkbox" name="autologout" id="autologout" {if $isOpac == true}checked="checked"{/if}> Log me out after requesting the item.</label>
					<input type="hidden" name="holdType" value="hold">
				</div>
			</div>
		</fieldset>
	</form>
</div>
{/strip}
