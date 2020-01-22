{strip}
	<div id="page-content" class="col-xs-12">

		<h2>{translate text='Reset My PIN'}</h2>
		<div class="alert alert-info">
			<p>Please enter a new PIN.</p>
			<p><strong>&bull; {if $alphaNumericOnlyPins}Use numbers and letters.{else}Use only numbers.{/if}</strong></p>
			<p><strong>&bull; Your new PIN must be at least {$pinMinimumLength} characters in length.</strong></p>
		</div>

		<form id="resetPin" method="POST" action="/MyAccount/ResetPin" class="form-horizontal">
			{if $resetToken}
				<input type="hidden" name="resetToken" value="{$resetToken}">
			{/if}
			{if $userID}
				<input type="hidden" name="uid" value="{$userID}">
			{/if}
			<div class="form-group">
				<div class="col-xs-4"><label for="pin1" class="control-label">{translate text='New PIN'}:</label></div>
				<div class="col-xs-8">
					<input type="password" name="pin1" id="pin1" value="" size="4" maxlength="30" class="form-control required{if $numericOnlyPins} digits{else}{if $alphaNumericOnlyPins} alphaNumeric{/if}{/if}">
				</div>
			</div>
			<div class="form-group">
				<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
				<div class="col-xs-8">
					<input type="password" name="pin2" id="pin2" value="" size="4" maxlength="30" class="form-control required{if $numericOnlyPins} digits{else}{if $alphaNumericOnlyPins} alphaNumeric{/if}{/if}">
				</div>
			</div>
			<div class="form-group">
				<div class="col-xs-8 col-xs-offset-4">
					<input id="resetPinSubmit" name="submit" class="btn btn-primary" type="submit" value="Reset My Pin">
				</div>
			</div>
		</form>
	</div>
{/strip}
<script type="text/javascript">
	{literal}
	$(function () {
		$("#resetPin").validate({
			rules: {
				pin1: {minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}0{/if}{literal}},
				pin2: {
					equalTo: "#pin1",
					minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}0{/if}{literal}
				}
			}
		});
	});
	{/literal}
</script>
