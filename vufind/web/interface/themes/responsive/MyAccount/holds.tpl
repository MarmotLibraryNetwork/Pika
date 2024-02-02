{strip}
	{if $loggedIn}
		{include file="MyAccount/patronWebNotes.tpl"}
		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		{* Check to see if there is data for the section *}
		{include file="MyAccount/libraryHoursMessage.tpl"}

		<p class="holdSectionBody">
	{if $offline}
		<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your holds at this time.</div>
	{else}

		<p id="overdrive_holds_inclusion_notice">
			{translate text="Items on hold include titles in Overdrive."}
		</p>

		{foreach from=$recordList item=sectionData key=sectionKey}
			<h3>{if $sectionKey == 'available'}Holds Ready For Pickup{else}Pending Holds{/if}</h3>
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
					<select name="{$sectionKey}HoldSort" id="{$sectionKey}HoldSort" class="form-control" onchange="Pika.Account.changeAccountSort($(this).val(), '{$sectionKey}HoldSort');">
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
						<input id="selectAll{$sectionKey}" type="checkbox" onclick="Pika.toggleCheckboxes('.titleSelect{$sectionKey}', '#selectAll{$sectionKey}');" title="Select All/Deselect All">
						{* element Id needs to be unique for each section so that toggleCheckboxes() functions *}
					</div>
				</div>
				<div class="striped">
					{foreach from=$recordList.$sectionKey item=record name="recordLoop"}
						{if strtolower($record.holdSource) == 'ils'}
							{include file="MyAccount/ilsHold.tpl" record=$record section=$sectionKey resultIndex=$smarty.foreach.recordLoop.iteration}
						{elseif $record.holdSource == 'OverDrive'}
							{include file="MyAccount/overdriveHold.tpl" record=$record section=$sectionKey resultIndex=$smarty.foreach.recordLoop.iteration}
						{else}
							<div class="row">
								Unknown record source {$record.holdSource}
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
		<h3>Offline Holds</h3>
		<p class="alert alert-warning">
			These titles will have a hold placed on them when access to the circulation system is restored.
		</p>

		{foreach from=$offlineHolds item=offlineHold name="recordLoop"}
			{include file="MyAccount/offlineHold.tpl" record=$offlineHold section='Offline' resultIndex=$smarty.foreach.recordLoop.iteration}
		{/foreach}

	{/if}

	{else} {* Check to see if user is logged in *}
      {include file="MyAccount/loginRequired.tpl"}
	{/if}
{/strip}