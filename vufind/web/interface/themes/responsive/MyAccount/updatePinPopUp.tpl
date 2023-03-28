{strip}
	<div class="alert alert-info">
		<p>Please update your {translate text='pin'}. If you have any questions, please contact your library.</p>
		<p class="alert alert-warning"> This action is required to access your account.</p>
		<br>
		<p><strong>&bull; {if $alphaNumericOnlyPins}Use numbers and letters.{elseif $numericOnlyPins}Use only numbers.{else}Use numbers and/or letters.{/if}</strong></p>
		{if $pinMinimumLength == $pinMaximumLength}
			<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} characters in length.</strong></p>
		{else}
			<p><strong>&bull; Your new {translate text='pin'} must be {$pinMinimumLength} to {$pinMaximumLength} characters in length.</strong></p>
		{/if}
    {if $sierraTrivialPin}
			<br>
			<p><strong>&bull; Do not repeat a number or letter more than two times in a row (<code>1112</code>, <code>abcdabcd</code>, or <code>zeee</code> will not work).</strong></p>
			<p><strong>&bull; Do not repeat the same two numbers or letters in a row (<code>1212</code>, <code>queue</code>, or <code>banana</code> will not work).</strong></p>
    {/if}
	</div>

    {* Copied from profile.tpl *}
    {if $pinUpdateErrors}
		{foreach from=$pinUpdateErrors item=errorMsg}
			{if strpos($errorMsg, 'success')}
				<div id="errorMsg"  class="alert alert-success">{$errorMsg}</div>
			{else}
				<div id="successMsg" class="alert alert-danger">{$errorMsg}</div>
			{/if}
		{/foreach}
		{else}
			<div id="errorMsg"  class="alert alert-danger" style="display: none"></div>
			<div id="successMsg" class="alert alert-success" style="display: none"></div>
	 {/if}

    {* Copied from profile.tpl *}
	<form action="/MyAccount/UpdatePin" method="post" class="form-horizontal" id="pinForm">
	<div class="form-group">
		<div class="col-xs-4"><label for="pin" class="control-label">{translate text='Old PIN'}:</label></div>
		<div class="col-xs-8">
			<input type="password" name="pin" id="pin" value="" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
				{* No size limits in case previously set password doesn't meet current restrictions *}
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin1" class="control-label">{translate text='New PIN'}:</label></div>
		<div class="col-xs-8">
			<input type="password" name="pin1" id="pin1" value="" size="4" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
		<div class="col-xs-8">
			<input type="password" name="pin2" id="pin2" value="" size="4" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-8 col-xs-offset-4">
			{if $showForgotPinLink}
				<p class="help-block">
					<strong>{translate text="Forgot PIN?"}</strong>&nbsp;
					<a href="/MyAccount/EmailResetPin">{translate text='Reset My PIN'}</a>
				</p>
			{/if}
		</div>
	</div>
	</form>

	<script type="text/javascript">
      {* input classes  'required', 'digits', 'alphaNumeric' are validation rules for the validation plugin *}
      {literal}
			$("#pinForm").validate({
				rules: {
					pin1: {minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal},
						maxlength:{/literal}{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}{literal}},
					pin2: {
						equalTo: "#pin1",
						minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal}
					}
				},
				submitHandler: function(){
					Pika.Account.updatePin();
				}
			});
      {/literal}
	</script>

{/strip}