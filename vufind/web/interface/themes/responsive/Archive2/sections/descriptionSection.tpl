{strip}
	{if $description}
		<div class="row">
			<div class="result-value col-sm-12">{$description}</div>
		</div>
		<hr />
	{/if}
	{if $physical_description}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Physical Description:</div>
			<div class="result-value col-sm-8">{$physical_description}</div>
		</div>
	{/if}
	{if $languageName}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Language:</div>
			<div class="result-value col-sm-8">{$languageName}</div>
		</div>
	{/if}
{/strip}
