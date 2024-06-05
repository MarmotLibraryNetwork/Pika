{strip}
	<form id="freeze-hold-form"{* role="form" Assigning form role to html form tags is not neccessary *}>
		<input type="hidden" name="holdId" value="{$holdId}" id="holdId">
		<input type="hidden" name="patronId" value="{$patronId}" id="patronId">
		<input type="hidden" name="recordId" value="{$recordId}" id="recordId">
		<div class="form-group">
			<label for="reactivationDate">Select the date when you want the hold {translate text="thawed"}.</label>
			<input type="text" name="reactivationDate" id="reactivationDate" class="form-control input-sm{if !$reactivateDateNotRequired} required{/if}"{if !$reactivateDateNotRequired} aria-required="true"{/if}>
		</div>
		{if $reactivateDateNotRequired}
			<p class="alert alert-info">
				If a date is not selected, the hold will be {translate text="frozen"} until you {translate text="thaw"} it.
			</p>
		{/if}
	</form>
	<script>
		{literal}
		$(function(){
			$('#freeze-hold-form').validate({
				submitHandler: function(){
					Pika.Account.doFreezeHoldWithReactivationDate('#doFreezeHoldWithReactivationDate');
				}
			});
			$( "#reactivationDate" ).datepicker({
				startDate: Date(),
				orientation:"bottom"
			});
		});
		{/literal}
	</script>
{/strip}