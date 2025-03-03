{*
 Should be called from statusIndeicator.tpl
 The button is paired with a hidden div that will be displayed in the modal pop-up
 *}
{strip}
	{if $statusInformation.hasAHomePickupItem}
		<button class="homePickupButton btn btn-link" onclick="return Pika.showElementInPopup('Home Pickup', '#homePickupPopup_{$statusInformation.id|escapeCSS}');">
			Has Home Pickup Items
		</button>
	{/if}
{/strip}