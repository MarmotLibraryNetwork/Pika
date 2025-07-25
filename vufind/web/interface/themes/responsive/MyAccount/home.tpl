{strip}
	<div id="account-summary-content">
		{if $loggedIn}
			{include file="MyAccount/patronWebNotes.tpl"}

			{* Alternate Mobile MyAccount Menu *}
			{include file="MyAccount/mobilePageHeader.tpl"}

			<h1 role="heading" aria-level="1" class="h2">{translate text='Account Summary'}</h1>
			<div>
				{if $offline}
				<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your check outs and holds at this time.</div>
				{else}

				You currently have:
				<ul>
					<li><strong><span class="checkouts-placeholder"><img src="/images/loading.gif" alt="loading"></span></strong> titles <a href="/MyAccount/CheckedOut">checked out</a></li>
					<li><span id="account-summary-holds"><strong><span class="holds-placeholder"><img src="/images/loading.gif" alt="loading"></span></strong> titles on <a href="/MyAccount/Holds">hold</a></span></li>
					{* Disable this menu option since booked items can only be seen in the Classic OPAC
					{if $enableMaterialsBooking}
					<li><strong><span class="bookings-placeholder"><img src="/images/loading.gif" alt="loading"></span></strong> titles <a href="/MyAccount/Bookings">scheduled</a></li>
					{/if}*}
				</ul>
				{* TODO: Show an alert if any titles are expired or are going to expire *}
				{* TODO: Show an alert if any titles ready for pickup *}
			</div>
				{/if}
			{if $showRatings}
				<h2 class="h3">{translate text='Recommended for you'}</h2>
				{if !$hasRatings}
					<p>
						You have not rated any titles.
						If you rate titles, we can provide you with suggestions for titles you might like to read.
						Suggestions are based on titles you like (rated 4 or 5 stars) and information within the catalog.
						Library staff does not have access to your suggestions.
					</p>
				{else}
					<p>Based on the titles you have <a href="/MyAccount/MyRatings">rated</a>, we have <a href="/MyAccount/SuggestedTitles">suggestions for you</a>.  To improve your suggestions keep rating more titles.</p>
					{foreach from=$suggestions item=suggestion name=recordLoop}
						<div class="result {if ($smarty.foreach.recordLoop.iteration % 2) == 0}alt{/if} record{$smarty.foreach.recordLoop.iteration}">
							{$suggestion}
						</div>
					{/foreach}
				{/if}
			{/if}
		{else}
        {include file="MyAccount/loginRequired.tpl"}
		{/if}
	</div>
{/strip}