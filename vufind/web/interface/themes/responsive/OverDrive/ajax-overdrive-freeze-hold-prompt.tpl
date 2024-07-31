{strip}
<form method="post" action="" id="overdriveFreezeHoldPromptsForm">
	<div>
		<input type="hidden" name="overDriveId" value="{$overDriveId}">
		<input type="hidden" name="patronId" id="patronId" value="{$patronId}">

      {include file="OverDrive/ajax-overdrive-hold-notification-email.tpl"}
	</div>

	<div class="form-group">
		<label for="thawDate">Select the date when you want the hold {translate text="thawed"}.</label>
		<input type="text" name="thawDate" id="thawDate" class="form-control required input-sm datePika" value="{if $thawDate}{$thawDate}{else}{/if}">
	</div>
		{*<p class="alert alert-info">
			If a date is not selected, the hold will be {translate text="frozen"} until you {translate text="thaw"} it.
		</p>*}
	<script>
{literal}
$(function(){
	$("#overdriveFreezeHoldPromptsForm").validate({
		submitHandler: function(){
			Pika.OverDrive.processFreezeOverDriveHoldPrompts()
		}
	});
	$( "#thawDate" ).datepicker({
		format: "mm-dd-yyyy",
		startDate: Date(),
	});
});
{/literal}
{*
 	Removed orientation:"bottom" to allow the default orientation:"auto" detect which direction to use
	to avoid going over the browser's view edge
 *}
	</script>
</form>
{/strip}