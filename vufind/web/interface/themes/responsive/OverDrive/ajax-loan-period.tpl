{* this doesn't look to be used any more. pascal 8-1-2018
checkoutOverDriveItemStep2() function no longer exists
*}

<div id="popupboxHeader" class="header">
	{translate text="Loan Period"}
	<a href="#" onclick='hideLightbox();return false;' class="closeIcon">Close <img src="/images/silk/cancel.png" alt="close" /></a>
</div>
<div id="popupboxContent" class="content">
	<form method="post" action=""> 
		<div>
			<input type="hidden" name="overdriveId" value="{$overDriveId}"/>
			<input type="hidden" name="formatId" value="{$formatId}"/>
			<label for="loanPeriod">{translate text="How long would you like to checkout this title?"}</label>
			<select name="loanPeriod" id="loanPeriod">
				{foreach from=$loanPeriods item=loanPeriod}
					<option value="{$loanPeriod}">{$loanPeriod} days</option>
				{/foreach}
			</select> 
			<input type="submit" name="submit" value="Check Out" onclick="return checkoutOverDriveItemStep2('{$overDriveId}', '{$formatId}')"/>
		</div>
	</form>
</div>