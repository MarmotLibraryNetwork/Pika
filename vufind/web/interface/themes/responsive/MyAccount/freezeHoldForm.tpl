{strip}
	<h3>{translate text= "Freezing"} {$holdSelected|@count} Hold{if count($holdSelected) > 1}s{/if}</h3>
	{if $reinstateDate}
		<form name="freezeHolds">
			<div class="row">
				<div class="col-sm-3">
					<label for="suspendDate">Date to {translate text="Thaw Hold"}{if count($holdSelected) > 1}s{/if}: </label>
				</div>
				<div class="col-sm-9">
					<input type="date" id="suspendDate" name="suspendDate" value="{$reinstate|date_format:"%Y-%m-%d"}" >
				</div>
			</div>
		</form>
		{/if}
{/strip}