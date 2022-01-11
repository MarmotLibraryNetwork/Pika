{strip}
	{if $cancelResults.title && !is_array($cancelResults.title)}
		{* for single item results *}
		<p><strong>{$cancelResults.title|removeTrailingPunctuation}</strong></p>
	{/if}
	<div class="contents">
			{$cancelResults.numCancelled}
			{$totalCanceled}
		{if $cancelResults.success}
        {if $cancelResults.numCancelled == $totalCanceled}
					<div class="alert alert-success"><strong>The hold{if $cancelResults.numCancelled >1}s were{else} was{/if} {translate text="canceled"} successfully.</div>
        {elseif $cancelResults.numCancelled > 0 and $cancelResults.numCancelled!=$totalCanceled}
					<div class="alert alert-warning"><strong>{$cancelResults.numCancelled} of {$totalCanceled}</strong> holds were {translate text="canceled"} successfully.</div>
        {else}
					<div class="alert alert-danger"><strong>Your hold{if $cancelResults.numCancelled >1}s{/if} could not be {translate text="canceled"}.</strong></div>
        {/if}
				{else}
			<div class="alert alert-danger"><strong>Your hold{if $cancelResults.numCancelled >1}s{/if} could not be {translate text="canceled"}.</strong></div>
		{/if}
	</div>
{/strip}