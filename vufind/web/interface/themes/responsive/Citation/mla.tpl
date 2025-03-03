{if $mlaDetails.format}
	{if $mlaDetails.format == "DVD"}
		<span style="font-style: italic;">{$mlaDetails.title|escape}</span>
		{if $mlaDetails.authors}{$mlaDetails.authors|escape}.{/if}
		{if $mlaDetails.edition}{$mlaDetails.edition|escape}{/if}
		{if $mlaDetails.publisher}{$mlaDetails.publisher|escape},{/if}
		{if $mlaDetails.year}{$mlaDetails.year|escape}.{/if}
	{else}
		{if $mlaDetails.authors}{$mlaDetails.authors|escape}.{/if}
		<span style="font-style: italic;">{$mlaDetails.title|escape}</span>
		{if $mlaDetails.edition}{$mlaDetails.edition|escape}{/if}
		{if $mlaDetails.publisher}{$mlaDetails.publisher|escape},{/if}
		{if $mlaDetails.year}{$mlaDetails.year|escape}.{/if}
	{/if}
{else}
	{if $mlaDetails.authors}{$mlaDetails.authors|escape}.{/if}
	<span style="font-style: italic;">{$mlaDetails.title|escape}</span>
	{if $mlaDetails.edition}{$mlaDetails.edition|escape}{/if}
	{if $mlaDetails.publisher}{$mlaDetails.publisher|escape},{/if}
	{if $mlaDetails.year}{$mlaDetails.year|escape}.{/if}
{/if}
