{strip}
	<div class="alert alert-info">
		<p>Please update your {translate text='pin'}. If you have any questions, please contact your library.</p>
		<p class="alert alert-warning"> This action is required to access your account.</p>
		<br>
		{include file="MyAccount/passwordRequirements.tpl"}
	</div>

    {* Copied from profile.tpl *}
    {if $pinUpdateErrors}
		{foreach from=$pinUpdateErrors item=errorMsg}
			{if strpos($errorMsg, 'success')}
				<div id="errorMsg" class="alert alert-success">{$errorMsg}</div>
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
				<input type="password" name="pin" id="pin" value="" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}" aria-required="true">
          {* No size limits in case previously set password doesn't meet current restrictions *}
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button aria-label="show {translate text='PIN'}" onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin');" class="btn btn-default" type="button" ><span class="glyphicon glyphicon-eye-open" aria-hidden="true" title="Show {translate text='PIN'}"><div class="hiddenText">Show {translate text='PIN'}</div></span><span class="glyphicon glyphicon-eye-close" style="display: none" title="Hide {translate text='PIN'}"><div class="hiddenText">Hide {translate text='PIN'}</div></span></button>
	      </span>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin1" class="control-label">{translate text='New PIN'}:</label></div>
		<div class="col-xs-8">
			<div class="input-group">
				<input type="password" name="pin1" id="pin1" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}" aria-required="true">
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button aria-label="show {translate text='PIN'}" onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin1')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" title="Show {translate text='PIN'}"><div class="hiddenText">Show {translate text='PIN'}</div></span><span class="glyphicon glyphicon-eye-close" style="display: none" title="Hide {translate text='PIN'}"><div class="hiddenText">Hide {translate text='PIN'}</div></span></button>
	      </span>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-xs-4"><label for="pin2" class="control-label">{translate text='Re-enter New PIN'}:</label></div>
		<div class="col-xs-8">
			<div class="input-group">
				<input type="password" name="pin2" id="pin2" value="" size="{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}" aria-required="true">
				<span class="input-group-btn" style="vertical-align: top"{* Override so button stays in place when input requirement message displays *}>
	        <button onclick="$('span', this).toggle(); return Pika.pwdToText('pin2')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" aria-hidden="true" title="Show {translate text='PIN'}"></span><span class="glyphicon glyphicon-eye-close" style="display: none" aria-hidden="true" title="Hide {translate text='PIN'}"></span></button><button aria-label="show {translate text='PIN'}" onclick="$('span', this).toggle(); $(this).attr('aria-label',$(this).children('span:visible').children('div').text()); return Pika.pwdToText('pin2')" class="btn btn-default" type="button"><span class="glyphicon glyphicon-eye-open" title="Show {translate text='PIN'}"><div class="hiddenText">Show {translate text='PIN'}</div></span><span class="glyphicon glyphicon-eye-close" style="display: none" title="Hide {translate text='PIN'}"><div class="hiddenText">Hide {translate text='PIN'}</div></span></button>
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