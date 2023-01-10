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
	</div>

	<div class="alert alert-danger" id="errorMsg" style="display: none"></div>
	<div class="alert alert-success" id="successMsg" style="display: none"></div>


    {* Copied from profile.tpl *}
    {if $pinUpdateErrors}
		{foreach from=$pinUpdateErrors item=errorMsg}
			{if strpos($errorMsg, 'success')}
				<div class="alert alert-success">{$errorMsg}</div>
			{else}
				<div class="alert alert-danger">{$errorMsg}</div>
			{/if}
		{/foreach}
	{/if}

    {* Copied from profile.tpl *}
	<form action="/MyAccount/UpdatePin" method="post" class="form-horizontal" id="pinForm">
	<div class="form-group">
		<div class="col-xs-4"><label for="pin" class="control-label">{translate text='Old PIN'}:</label></div>
		<div class="col-xs-8">
			<input type="password" name="pin" id="pin" value="" size="4" maxlength="{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}" class="form-control required{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}">
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
              {if $useEmailResetPin}
								<a href="/MyAccount/EmailResetPin">{translate text='Reset My PIN'}</a>
                  {*
						{else}
							<a href="/MyAccount/EmailPin">E-mail my PIN</a>
									*}
              {/if}
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