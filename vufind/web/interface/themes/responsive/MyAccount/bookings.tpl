{strip}
	{if $loggedIn}
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class="availableHoldsNoticePlaceHolder"></span>

		<h1 role="heading" aria-level="1" class="h2">My Scheduled Items</h1>

		{include file="MyAccount/libraryHoursMessage.tpl"}

		{if $offline}
			<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your scheduled items at this time.</div>
		{else}

			<p class="alert alert-info">If you have any currently scheduled items, they can be viewed in the <a href="{$classicBookingUrl}">classic site.</a> You will need to log in with the same credentials you use here.</p>
{*
			{if !empty($recordList)}

				<p class="alert alert-info">
					{translate text="booking summary"}
				</p>


				<div class="bookingsWithSelected">
					<form id="withSelectedHoldsFormTop">
						<div>
							<input type="hidden" name="withSelectedAction" value="">
							<div id="bookingsUpdateSelectedTop" class="bookingsUpdateSelected btn-group">
								<input class="btn btn-sm btn-warning" name="cancelSelected" value="Cancel Selected" onclick="return Pika.Account.cancelSelectedBookings()">
								<input class="btn btn-sm btn-danger" name="cancelAll" value="Cancel All" onclick="return Pika.Account.cancelAllBookings()">
									*}
{*<input type="submit" class="btn btn-sm btn-default" id="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}Bottom" name="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}" value="Export to Excel">*}{*

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

				*}
{* Code to handle updating multiple bookings at one time *}{*

				<br>
				<div class="bookingsWithSelected">
					<form id="withSelectedHoldsFormBottom">
						<div>
							<input type="hidden" name="withSelectedAction" value="">
							<div id="bookingsUpdateSelectedBottom" class="bookingsUpdateSelected btn-group">
								<input class="btn btn-sm btn-warning" name="cancelSelected" value="Cancel Selected" onclick="return Pika.Account.cancelSelectedBookings()">
								<input class="btn btn-sm btn-danger" name="cancelAll" value="Cancel All" onclick="return Pika.Account.cancelAllBookings()">
								*}
{*<input type="submit" class="btn btn-sm btn-default" id="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}Bottom" name="exportToExcel{if $sectionKey=='available'}Available{else}Unavailable{/if}" value="Export to Excel">*}{*

							</div>
						</div>
					</form>
				</div>
			{else} *}
{* Check to see if records are available *}{*

				<p class="alert alert-warning">
					{translate text='You do not have any items scheduled.'}
				</p>
			{/if}
*}
		{/if}
	{else} {* Check to see if user is logged in *}
		{* This should never get displayed. Users should automatically be redirected to login page*}
		{include file="MyAccount/loginRequired.tpl"}
	{/if}
{/strip}