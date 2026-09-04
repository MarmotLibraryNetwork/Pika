{strip}
	{foreach from=$interview_locations item=loc}
		<div class="row archive-field-row">
			<div class="result-label col-md-4">Location:</div>
			<div class="result-value col-md-8">
				{if $loc.startDate || $loc.endDate}{if $loc.startDate}{$loc.startDate|escape}{/if}{if $loc.startDate && $loc.endDate} &ndash; {/if}{if $loc.endDate}{$loc.endDate|escape}{/if}<br>{/if}
				{if $loc.street}{$loc.street|escape}<br>{/if}
				{if $loc.address2}{$loc.address2|escape}<br>{/if}
				{if $loc.city}{$loc.city|escape}{/if}{if $loc.city && ($loc.state || $loc.zip)}, {/if}{if $loc.state}{$loc.state|escape} {/if}{if $loc.zip}{$loc.zip|escape}{/if}
				{if $loc.county || $loc.otherRegion || $loc.country}<br>{if $loc.county}{$loc.county|escape}{/if}{if $loc.county && ($loc.otherRegion || $loc.country)}, {/if}{if $loc.otherRegion}{$loc.otherRegion|escape}{/if}{if $loc.otherRegion && $loc.country}, {/if}{if $loc.country}{$loc.country|escape}{/if}{/if}
			</div>
		</div>
	{/foreach}
{/strip}
