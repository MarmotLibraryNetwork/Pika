{strip}
	<h1 role="heading" aria-level="1" class="h2">{translate text='Update My PIN'}</h1>
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

		{* Not likely used, but added just in case *}
	{if $message}{* Errors for Full Login Page *}
		<p class="alert alert-danger" id="loginError" >{$message|translate}</p>
	{/if}

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
		<div class="form-group">
			<div class="col-xs-8 col-xs-offset-4">
				<input type="submit" value="{translate text='Update PIN'}" name="update" class="btn btn-primary">

{*          {if $followup}<input type="hidden" name="followup" value="{$followup}">{/if}*}
          {if $followupModule}<input type="hidden" name="followupModule" value="{$followupModule}">{/if}
          {if $followupAction}<input type="hidden" name="followupAction" value="{$followupAction}">{/if}
          {if $recordId}<input type="hidden" name="recordId" value="{$recordId|escape:"html"}">{/if}
          {*TODO: figure out how & why $recordId is set *}
          {if $id}<input type="hidden" name="id" value="{$id|escape:"html"}">{/if}{* For storing at least the list id when logging in to view a private list *}
          {if $comment}<input type="hidden" id="comment" name="comment" value="{$comment|escape:"html"}">{/if}
{*          {if $cardNumber}<input type="hidden" name="cardNumber" value="{$cardNumber|escape:"html"}">{/if}*}{* for masquerading *}
          {if $returnUrl}<input type="hidden" name="returnUrl" value="{$returnUrl}">{/if}

			</div>
		</div>
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
					}
				});
        {/literal}
		</script>
	</form>
{/strip}