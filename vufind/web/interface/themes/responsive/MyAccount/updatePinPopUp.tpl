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
			<p><strong>&bull; Do not repeat a character three or more times in a row (for example: <code>1112</code>, <code>zeee</code>, or <code>x7gp3333</code> will not work).</strong></p>
			<p><strong>&bull; Do not repeat a set of characters two or more times in a row (for example: <code>1212</code>, <code>abab</code>, <code>abcabc</code>, <code>abcdabcd</code>, <code>queue</code>, or <code>banana</code> will not work).</strong></p>
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
		<div class="col-xs-4"><label for="pin" class="control-label">{translate text='Default PIN'}:</label></div>
		<div class="col-xs-8">
			<div class="input-group">
				<input type="password" name="pin" id="pin" value="" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
          {* No size limits in case previously set password doesn't meet current restrictions *}
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button onclick="$('span', this).toggle(); return Pika.pwdToText('pin')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" aria-hidden="true" title="Show {translate text='PIN'}"></span><span class="glyphicon glyphicon-eye-close" style="display: none" aria-hidden="true" title="Hide {translate text='PIN'}"></span></button>
	      </span>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin1" class="control-label">{translate text='New PIN'}:</label></div>
		<div class="col-xs-8">
			<div class="input-group">
				<input type="password" name="pin1" id="pin1" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button onclick="$('span', this).toggle(); return Pika.pwdToText('pin1')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" aria-hidden="true" title="Show {translate text='PIN'}"></span><span class="glyphicon glyphicon-eye-close" style="display: none" aria-hidden="true" title="Hide {translate text='PIN'}"></span></button>
	      </span>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
		<div class="col-xs-8">
			<div class="input-group">
				<input type="password" name="pin2" id="pin2" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button onclick="$('span', this).toggle(); return Pika.pwdToText('pin2')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" aria-hidden="true" title="Show {translate text='PIN'}"></span><span class="glyphicon glyphicon-eye-close" style="display: none" aria-hidden="true" title="Hide {translate text='PIN'}"></span></button>
	      </span>
			</div>
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

	<script>
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
					$('#pinFormSubmitButton').attr("disabled", true); // Disable the button to prevent multiple submissions
					Pika.Account.updatePin();
				}
			});
      {/literal}
	</script>

{/strip}