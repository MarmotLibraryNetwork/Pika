{strip}
<form method="post" action="" id="overdriveFreezeHoldPromptsForm">
	<div>
		<input type="hidden" name="overDriveId" value="{$overDriveId}">
		<input type="hidden" name="patronId" id="patronId" value="{$patronId}">

			{include file="OverDrive/ajax-overdrive-hold-notification-email.tpl"}
	</div>

		<div class="alert alert-info">
			<p>The hold will be {translate text="frozen"} until you {translate text="thaw"} it. If you miss your hold on its first delivery, it will be {translate text="frozen"} until you {translate text="thaw"} it.</p>
			<p><strong>The hold is automatically canceled if it has been {translate text="frozen"} for 365 consecutive days.</strong></p>
		</div>
	<script>
{literal}
$(function(){
	$("#overdriveFreezeHoldPromptsForm").validate({
		submitHandler: function(){
			Pika.OverDrive.processFreezeOverDriveHoldPrompts()
		}
	});
/*
	$( "#thawDate" ).datepicker({
		format: "mm-dd-yyyy",
		startDate: "0",
		endDate: "+365d"
	});
*/
});
{/literal}
{*
 	Removed orientation:"bottom" to allow the default orientation:"auto" detect which direction to use
	to avoid going over the browser's view edge
 *}
	</script>
</form>
{/strip}