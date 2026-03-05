{strip}
	{if $loggedIn}
		{include file="MyAccount/patronWebNotes.tpl"}
		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		<h1 role="heading" aria-level="1" class="h2">Titles on Hold</h1>

		{* Check to see if there is data for the section *}
		{include file="MyAccount/libraryHoursMessage.tpl"}

		<p class="holdSectionBody">
	{if $offline}
		<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your holds at this time.</div>
	{else}

		{if $overDriveOfflineMode}
			<p class="alert alert-warning"><strong>Access to OverDrive is currently limited.</strong> We are unable to retrieve information about your OverDrive holds at this time.</p>
		{else}
			<p id="overdrive_holds_inclusion_notice">
				{translate text="Items on hold include titles in Overdrive."}
			</p>
		{/if}


		{foreach from=$recordList item=sectionData key=sectionKey}
			<h2 class="h3">{if $sectionKey == 'available'}Holds Ready For Pickup{else}Pending Holds{/if}</h2>
			<p class="alert alert-info">
				{if $sectionKey == 'available'}
					{translate text="available hold summary"}
					{*These titles have arrived at the library or are available online for you to use.*}
				{else}
					{translate text="These titles are currently checked out to other patrons."}  We will notify you{if not $notification_method or $notification_method eq 'Unknown' or $notification_method eq 'none'}{else} via {$notification_method}{/if} when a title is available.
					{* Only show the notification method when it is known and set *}
				{/if}
			</p>
			{if is_array($recordList.$sectionKey) && count($recordList.$sectionKey) > 0}
				<div id="pager" class="navbar form-inline">
					<label for="{$sectionKey}HoldSort" class="control-label">{translate text='Sort by'}:&nbsp;</label>
					<select name="{$sectionKey}HoldSort" id="{$sectionKey}HoldSort" class="form-control">
						{foreach from=$sortOptions[$sectionKey] item=sortDesc key=sortVal}
							<option value="{$sortVal}"{if $defaultSortOption[$sectionKey] == $sortVal} selected="selected"{/if}>{translate text=$sortDesc}</option>
						{/foreach}
					</select>

					{if !$hideCoversFormDisplayed}
						{* Display the Hide Covers switch above the first section that has holds; and only display it once *}
						<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
						{assign var="hideCoversFormDisplayed" value=true}
					{/if}
				</div>
				<div class="row result">
					<div class="col-sm-1">
						<input id="selectAll{$sectionKey}" type="checkbox" onclick="Pika.toggleCheckboxes('.titleSelect{$sectionKey}', '#selectAll{$sectionKey}');" title="Select All/Deselect All" aria-label="Select All/Deselect All">
						{* element Id needs to be unique for each section so that toggleCheckboxes() functions *}
					</div>
				</div>
				<div class="striped">
					{foreach from=$recordList.$sectionKey item=holdRecord}
						{if strtolower($holdRecord.holdSource) == 'ils'}
							{include file="MyAccount/ilsHold.tpl" record=$holdRecord section=$sectionKey resultIndex=$holdRecord@iteration}
						{elseif strtolower($holdRecord.holdSource) == 'overdrive'}
							{include file="MyAccount/overdriveHold.tpl" record=$holdRecord section=$sectionKey resultIndex=$holdRecord@iteration}
						{else}
							<div class="row">
								Unknown record source {$holdRecord.holdSource}
							</div>
						{/if}
					{/foreach}
				</div>


				{* Code to handle updating multiple holds at one time *}
				<br>
				<div class="holdsWithSelected{$sectionKey}">
					<form id="withSelectedHoldsFormBottom{$sectionKey}" action="{$fullPath}">
						<div>
							<input type="hidden" name="withSelectedAction" value="">
							<div id="holdsUpdateSelected{$sectionKey}Bottom" class="holdsUpdateSelected{$sectionKey}">
								<div class="btn-group">
								<input type="submit" class="btn btn-sm btn-warning" name="cancelSelected" value="Cancel Selected" onclick="return Pika.Account.cancelSelectedHolds();">
								{if $sectionKey=='unavailable'}<input type="submit" class="btn btn-sm btn-default" name="freezeSelected" value="{translate text="Freeze"} Selected" onclick="return Pika.Account.getFreezeHoldsForm();" >{/if}
								<input type="submit" class="btn btn-sm btn-default" id="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}Bottom" name="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}" value="Export to Excel" >
								</div>
							</div>
						</div>
					</form>
				</div>
				<script>
					{literal}
					// Setup sorting for holds
					document.addEventListener('DOMContentLoaded', function() {
						var selectElement = document.getElementById('{/literal}{$sectionKey}{literal}HoldSort');

						// Add event listener for click to sort options
						selectElement.addEventListener('click', function(e) {
							let val = checkSelectedOption(this);
							if(val !== null) {
								Pika.Account.changeAccountSort(val, '{/literal}{$sectionKey}{literal}HoldSort');
							}
						})

						// Add event listener for keypress (accessibility)
						selectElement.addEventListener('keypress', function(e) {
							let val = checkSelectedOption(this);
							if(e.key === 'Enter' && val !== null) {
								Pika.Account.changeAccountSort(val, '{/literal}{$sectionKey}{literal}HoldSort');
							}
						})
					});
					{/literal}
				</script>
			{else} {* Check to see if records are available *}
				{if $sectionKey == 'available'}
					{translate text='You do not have any holds that are ready to be picked up.'}
				{else}
					{translate text='You do not have any pending holds.'}
				{/if}

			{/if}
		{/foreach}
	{/if}
	{if !empty($offlineHolds)}
		<h2 class="h3">Offline Holds</h2>
		<p class="alert alert-warning">
			These titles will have a hold placed on them when access to the circulation system is restored.
		</p>

		{foreach from=$offlineHolds item=offlineHold}
			{include file="MyAccount/offlineHold.tpl" record=$offlineHold section='Offline' resultIndex=$offlineHold@iteration}
		{/foreach}

	{/if}

	{else} {* Check to see if user is logged in *}
      {include file="MyAccount/loginRequired.tpl"}
	{/if}
{/strip}
