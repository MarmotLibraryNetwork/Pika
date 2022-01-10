{strip}
	<div class="contents">

			{if $numFrozen == $totalFrozen}
				<div class="alert alert-success"><strong>All holds were {translate text="frozen"} successfully.</div>
			{elseif $numFrozen > 0 and $numFrozen!=$totalFrozen}
				<div class="alert alert-warning"><strong>{$freezeResults.numFrozen} of {$totalFrozen}</strong> holds were {translate text="frozen"} successfully.</div>
			{else}
				<div class="alert alert-danger"><strong>Your holds could not be {translate text="frozen"}.</strong></div>
			{/if}


	</div>
{/strip}