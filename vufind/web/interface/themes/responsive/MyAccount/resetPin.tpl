{strip}
	<div id="page-content" class="col-xs-12">

		<h2>{translate text='Reset My PIN'}</h2>
      {if $resetPinResult.error}
				<p class="alert alert-danger">{$resetPinResult.error}</p>
		{/if}
		{if ($resetPinResult === true)}
			<p class="alert alert-success">Your {translate text='pin'} has been reset.</p>
			<p>
				<a class="btn btn-primary" role="button" href="/MyAccount/Login">{translate text='Login'}</a>
			</p>
		{else}
			<div class="alert alert-info">
				<p>Please enter a new {translate text="pin"}.</p>
				<p><strong>&bull; {if $alphaNumericOnlyPins}Use numbers and letters.{elseif $numericOnlyPins}Use only numbers.{else}Use numbers and/or letters.{/if}</strong></p>
				{if $pinMinimumLength == $pinMaximumLength}
					<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} characters in length.</strong></p>
				{else}
					<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} to {$pinMaximumLength} characters in length.</strong></p>
				{/if}
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
						<input type="password" name="pin1" id="pin1" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
					</div>
				</div>
				<div class="form-group">
					<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
					<div class="col-xs-8">
						<input type="password" name="pin2" id="pin2" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
					</div>
				</div>
				<div class="form-group">
					<div class="col-xs-8 col-xs-offset-4">
						<input id="resetPinSubmit" name="submit" class="btn btn-primary" type="submit" value="Reset My Pin">
					</div>
				</div>
			</form>
		{/if}
	</div>
{/strip}
<script type="text/javascript">
	{literal}
	$(function () {
		$("#resetPin").validate({
			rules: {
				pin1: {
					minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal},
					maxlength:{/literal}{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}{literal}
				},
				pin2: {
					equalTo: "#pin1",
					minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal}
				}
			}
		});
	});
	{/literal}
</script>
