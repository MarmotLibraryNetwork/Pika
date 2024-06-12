{strip}
	{if $loggedIn}
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		<h1 role="heading" aria-level="1" class="h2">{translate text='Checked Out Titles'}</h1>

    {include file="MyAccount/libraryHoursMessage.tpl"}

		{if $offline}
			<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your {translate text='Checked Out Titles'|lower} at this time.</div>
		{else}

			{if $transList}
{*				<form action="/MyAccount/CheckedOut" method="get">*}
{*				<label for="accountSort" class="control-label">{translate text='Sort by'}:&nbsp;</label>*}
{*				<select name="accountSort" id="accountSort" class="form-control">*}
{*					{foreach from=$sortOptions item=sortDesc key=sortVal}*}
{*						<option value="{$sortVal}"{if $defaultSortOption == $sortVal} selected="selected"{/if}>{translate text=$sortDesc}</option>*}
{*					{/foreach}*}
{*				</select>*}
{*					<button type="submit" class="visuallyhidden">Sort</button>*}
{*				</form>*}
				<form id="renewForm" action="/MyAccount/RenewMultiple">
					<div id="pager" class="navbar form-inline">
						<label for="accountSort" class="control-label">{translate text='Sort by'}:&nbsp;</label>
						<select name="accountSort" id="accountSort" class="form-control">
							{foreach from=$sortOptions item=sortDesc key=sortVal}
								<option value="{$sortVal}"{if $defaultSortOption == $sortVal} selected="selected" data-selected=""{/if}>{translate text=$sortDesc}</option>
							{/foreach}
						</select>
						<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
					</div>

					<div class="btn-group">
						{if !$hasOnlyEContentCheckOuts}
							<button onclick="Pika.Account.renewSelectedTitles()" class="btn btn-sm btn-default">Renew Selected Items</button>
							<button onclick="Pika.Account.renewAll()" class="btn btn-sm btn-default">Renew All</button>
						{/if}
						<a href="/MyAccount/CheckedOut?exportToExcel{if isset($defaultSortOption)}&accountSort={$defaultSortOption}{/if}" class="btn btn-sm btn-default" id="exportToExcelTop">Export to Excel</a>
					</div>

					{if !$hasOnlyEContentCheckOuts}
						<div class="row result">
							<div class="col-sm-1">
								<input id="selectAll" type="checkbox" onclick="Pika.toggleCheckboxes('.titleSelect', '#selectAll');" title="Select All/Deselect All" aria-label="Select All/Deselect All">
							</div>
						</div>
					{/if}

					<div class="striped">
						{foreach from=$transList item=checkedOutTitle name=checkedOutTitleLoop key=checkedOutKey}
							{if strtolower($checkedOutTitle.checkoutSource) == 'ils'}
								{include file="MyAccount/ilsCheckedOutTitle.tpl" record=$checkedOutTitle resultIndex=$smarty.foreach.checkedOutTitleLoop.iteration}
							{elseif $checkedOutTitle.checkoutSource == 'OverDrive'}
								{include file="MyAccount/overdriveCheckedOutTitle.tpl" record=$checkedOutTitle resultIndex=$smarty.foreach.checkedOutTitleLoop.iteration}
							{elseif $checkedOutTitle.checkoutSource == 'Hoopla'}
								{include file="MyAccount/hooplaCheckedOutTitle.tpl" record=$checkedOutTitle resultIndex=$smarty.foreach.checkedOutTitleLoop.iteration}
							{else}
								<div class="row">
									Unknown record source {$checkedOutTitle.checkoutSource}
								</div>
							{/if}
						{/foreach}
					</div>

					{if translate('CheckedOut_Econtent_notice')}
							<br>
						<p class="alert alert-info">
							{translate text='CheckedOut_Econtent_notice'}
						</p>
					{/if}

					<div class="btn-group">
						{if !$hasOnlyEContentCheckOuts}
							<button onclick="Pika.Account.renewSelectedTitles()" class="btn btn-sm btn-default">Renew Selected Items</button>
							<button onclick="Pika.Account.renewAll()" class="btn btn-sm btn-default">Renew All</button>
						{/if}
						<a href="/MyAccount/CheckedOut?exportToExcel{if isset($defaultSortOption)}&accountSort={$defaultSortOption}{/if}" class="btn btn-sm btn-default" id="exportToExcelTop">Export to Excel</a>
					</div>
				</form>

			{else}
				{translate text='You do not have any items checked out'}.
			{/if}
		{/if}
	{else}
      {* This should never get displayed. Users should automatically be redirected to login page*}
      {include file="MyAccount/loginRequired.tpl"}
	{/if}
{/strip}
<script>
	{literal}
	// Setup sorting for checked out titles
	document.addEventListener('DOMContentLoaded', function() {
		var selectElement = document.getElementById('accountSort');
		
		// Add event listener for click to sort options
		selectElement.addEventListener('click', function(e) {
			let val = checkSelectedOption(this);
			if(val !== null) {
				//alert("Selected Value: " + val)
				Pika.Account.changeAccountSort(val);
			}
		})
		
		// Add event listener for keypress (accessibility)
		selectElement.addEventListener('keypress', function(e) {
			let val = checkSelectedOption(this);
			if(e.key === 'Enter' && val !== null) {
				Pika.Account.changeAccountSort(val);
			}
		}) 
	});
	{/literal}
</script>