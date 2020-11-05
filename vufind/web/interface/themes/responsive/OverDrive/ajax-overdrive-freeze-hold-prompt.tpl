{strip}
<form method="post" action="" id="overdriveFreezeHoldPromptsForm" class="form">
	<div>
		<input type="hidden" name="overDriveId" value="{$overDriveId}">
		<input type="hidden" name="patronId" id="patronId" value="{$patronId}">

      {include file="OverDrive/ajax-overdrive-hold-notification-email.tpl"}
	</div>

	<div class="form-group">
		<label for="thawDate">Select the date when you want the hold {translate text="thawed"}.</label>
		<input type="text" name="thawDate" id="thawDate" class="form-control input-sm datePika"{if $thawDate} value="{$thawDate}"{/if}>
	</div>
		<p class="alert alert-info">
			If a date is not selected, the hold will be {translate text="frozen"} until you {translate text="thaw"} it.
		</p>
	<script	type="text/javascript">
{literal}
$(function(){
	$(".form").validate({
		submitHandler: function(){
			Pika.OverDrive.processFreezeOverDriveHoldPrompts()
		}
	});
	$( "#thawDate" ).datepicker({
		format: "mm-dd-yyyy",
		startDate: Date(),
		orientation:"top"
	});
});
{/literal}
	</script>
</form>
{/strip}