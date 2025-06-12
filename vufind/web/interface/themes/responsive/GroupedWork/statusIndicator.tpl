{strip}
	{if $statusInformation.availableHere}
		{if $statusInformation.availableOnline}
			<div class="related-manifestation-shelf-status available">Available Online</div>
		{elseif $statusInformation.allLibraryUseOnly}
			{if $showItsHere && $isOpac}
				<div class="related-manifestation-shelf-status available">It's Here (library use only)</div>
			{else}
				<div class="related-manifestation-shelf-status available">{translate text='On Shelf (library use only)'}</div>
			{/if}
		{else}
			{if $showItsHere && $isOpac}
				<div class="related-manifestation-shelf-status available">It's Here {include file='GroupedWork/homePickupbutton.tpl'}</div>
			{else}
				<div class="related-manifestation-shelf-status available">{translate text='On Shelf'} {include file='GroupedWork/homePickupbutton.tpl'}</div>
			{/if}
		{/if}
	{elseif $statusInformation.availableLocally}
		{if $statusInformation.availableOnline}
			<div class="related-manifestation-shelf-status available">Available Online</div>
		{elseif $statusInformation.allLibraryUseOnly}
			<div class="related-manifestation-shelf-status available">{translate text='On Shelf (library use only)'}</div>
		{elseif $scopeType == 'Location'}
			<div class="related-manifestation-shelf-status availableOther">Available at another branch {include file='GroupedWork/homePickupbutton.tpl'}</div>
		{else}
{*			<div class="related-manifestation-shelf-status available">{translate text='On Shelf'}</div>*}
			<div class="related-manifestation-shelf-status available">{if empty($statusInformation.groupedStatus)}{translate text='On Shelf'}{else}{$statusInformation.groupedStatus}{/if} {include file='GroupedWork/homePickupbutton.tpl'}</div>
			{* Should be "On Shelf" most of the time, but this allows for other available statuses,
			like "On Display";
			like "Shelving"; or "Recently Returned" for Clearview *}
			{*TODO:  Need a condition when all the holdable copies are checked out and the remaining copies are library use only*}
		{/if}
	{elseif $statusInformation.availableOnline}
		<div class="related-manifestation-shelf-status available">Available Online</div>
	{elseif $statusInformation.allLibraryUseOnly}
		{if $isGlobalScope}
			<div class="related-manifestation-shelf-status available">{translate text='On Shelf (library use only)'}</div>
		{else}
			{if $statusInformation.available && $statusInformation.hasLocalItem}
				<div class="related-manifestation-shelf-status availableOther">{translate text='Checked Out/Available Elsewhere'} (library use only)</div>
			{elseif $statusInformation.available}
				<div class="related-manifestation-shelf-status availableOther">{translate text='Available from another library'} (library use only)</div>
			{else}
				<div class="related-manifestation-shelf-status checked_out">{translate text='Checked Out'} (library use only)</div>
			{/if}
		{/if}
	{elseif $statusInformation.available && $statusInformation.hasLocalItem}
		<div class="related-manifestation-shelf-status availableOther">{translate text='Checked Out/Available Elsewhere'} {include file='GroupedWork/homePickupbutton.tpl'}</div>
	{elseif $statusInformation.available}
		{if $isGlobalScope}
			<div class="related-manifestation-shelf-status available">{translate text='On Shelf'} {include file='GroupedWork/homePickupbutton.tpl'}</div>
		{else}
			<div class="related-manifestation-shelf-status availableOther">{translate text='Available from another library'}</div>
		{/if}
	{elseif $statusInformation.isAvailableToOrder}
		<div class="related-manifestation-shelf-status isAvailableToOrder">
				{if $statusInformation.groupedStatus}{$statusInformation.groupedStatus}{else}Withdrawn/Unavailable{/if}
		</div>
	{else}
		<div class="related-manifestation-shelf-status checked_out">
			{if $statusInformation.groupedStatus}{$statusInformation.groupedStatus}{else}Withdrawn/Unavailable{/if} {include file='GroupedWork/homePickupbutton.tpl'}
		</div>
	{/if}
	{if ($statusInformation.numHolds > 0 || $statusInformation.onOrderCopies > 0) && ($showGroupedHoldCopiesCount || $viewingIndividualRecord == 1)}
		<div class="smallText">
			{if $statusInformation.numHolds > 0}
				{$statusInformation.copies} {if $statusInformation.copies == 1}copy{else}copies{/if}, {$statusInformation.numHolds} {if $statusInformation.numHolds == 1}person is{else}people are{/if} on the wait list.
			{/if}
			{if $statusInformation.volumeHolds}
				<br>
				{foreach from=$statusInformation.volumeHolds item=volumeHoldInfo}
					&nbsp;&nbsp;{$volumeHoldInfo.numHolds} waiting for {$volumeHoldInfo.label}<br>
				{/foreach}
			{/if}
			{if $statusInformation.onOrderCopies > 0}
				<br>
				{if $showOnOrderCounts}
					{$statusInformation.onOrderCopies} {if $statusInformation.onOrderCopies == 1}copy{else}copies{/if} on order.
				{else}
					{if $statusInformation.totalCopies > 0}
						Additional copies on order
					{else}
						Copies on order
					{/if}
				{/if}
			{/if}

		</div>
	{/if}
	{if $statusInformation.hasAHomePickupItem}
		{* Triggered by button(s) from homePickupbutton.tpl *}
		<div id="homePickupPopup_{$statusInformation.id|escapeCSS}" style="display: none;">
			<p class="alert alert-info">You can place holds on the following items, but <strong>they must be picked up at the library location that owns the item.</strong></p>
			{if !empty($statusInformation.homePickupLocations)}
				<table class="table table-striped table-bordered">
					<tr>
						<th>Location</th>
						<th>Call number</th>
						<th>Status</th>
					</tr>
					{foreach from=$statusInformation.homePickupLocations item=item}
						<tr>
							<td>{$item.location}</td>
							<td>{$item.callnumber}</td>
							<td>{$item.status}</td>
						</tr>
					{/foreach}
				</table>
			{/if}

		</div>
	{/if}
{/strip}