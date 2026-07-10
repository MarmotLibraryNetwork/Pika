{strip}
	{if $raceEthnicity}
		<div class="row">
			<div class="result-label col-md-4">Race and Ethnicity: </div>
			<div class="result-value col-md-8">
				{implode subject=$raceEthnicity}
			</div>
		</div>
	{/if}
	{if $gender}
		<div class="row">
			<div class="result-label col-md-4">Gender Expression/Identity: </div>
			<div class="result-value col-md-8">
				{implode subject=$gender}
			</div>
		</div>
	{/if}
{/strip}