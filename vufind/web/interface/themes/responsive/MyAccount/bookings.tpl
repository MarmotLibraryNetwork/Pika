{strip}
	{if $loggedIn}
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		<h3>My Scheduled Items</h3>

    {include file="MyAccount/libraryHoursMessage.tpl"}

  {if $offline}
		<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your scheduled items at this time.</div>
	{else}

			{if !empty($recordList)}

				<p class="alert alert-info">
            {translate text="booking summary"}
				</p>


				<div class="bookingsWithSelected">
					<form id="withSelectedHoldsFormTop">
						<div>
							<input type="hidden" name="withSelectedAction" value="" >
							<div id="bookingsUpdateSelectedTop" class="bookingsUpdateSelected btn-group">
								<input type="submit" class="btn btn-sm btn-warning" name="cancelSelected" value="Cancel Selected" onclick="return Pika.Account.cancelSelectedBookings()">
								<input type="submit" class="btn btn-sm btn-danger" name="cancelAll" value="Cancel All" onclick="return Pika.Account.cancelAllBookings()">
                  {*<input type="submit" class="btn btn-sm btn-default" id="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}Bottom" name="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}" value="Export to Excel" />*}
							</div>
						</div>
					</form>
				</div>
					<br>

					<div class="striped">
						{foreach from=$recordList item=record name="recordLoop"}
								{include file="MyAccount/bookedItem.tpl" myBooking=$record resultIndex=$smarty.foreach.recordLoop.iteration}
						{/foreach}
					</div>

					{* Code to handle updating multiple bookings at one time *}
					<br>
					<div class="bookingsWithSelected">
						<form id="withSelectedHoldsFormBottom">
							<div>
								<input type="hidden" name="withSelectedAction" value="" >
								<div id="bookingsUpdateSelectedBottom" class="bookingsUpdateSelected btn-group">
									<input type="submit" class="btn btn-sm btn-warning" name="cancelSelected" value="Cancel Selected" onclick="return Pika.Account.cancelSelectedBookings()">
									<input type="submit" class="btn btn-sm btn-danger" name="cancelAll" value="Cancel All" onclick="return Pika.Account.cancelAllBookings()">
									{*<input type="submit" class="btn btn-sm btn-default" id="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}Bottom" name="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}" value="Export to Excel" />*}
								</div>
							</div>
						</form>
					</div>
				{else} {* Check to see if records are available *}
					<p class="alert alert-warning">
              {translate text='You do not have any items scheduled.'}
					</p>
			{/if}
	{/if}
		</div>
		{* TODO: sorting Bookings listings *}
{*		<script type="text/javascript">
			$(document).ready(function() {literal} { {/literal}
				$("#holdsTableavailable").tablesorter({literal}{cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader', headers: { 0: { sorter: false}, 3: {sorter : 'date'}, 4: {sorter : 'date'}, 7: { sorter: false} } }{/literal});
				{literal} }); {/literal}
		</script>*}
	{else} {* Check to see if user is logged in *}
      {* This should never get displayed. Users should automatically be redirected to login page*}
      {include file="MyAccount/loginRequired.tpl"}
	{/if}
{/strip}