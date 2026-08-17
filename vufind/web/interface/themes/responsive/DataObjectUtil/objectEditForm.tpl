{* Errors *}
{if isset($errors) && count($errors) > 0}
	<div id="errors" class="alert alert-error">
	{foreach from=$errors item=error}
		<div id="error">{$error}</div>
	{/foreach}
	</div>
{/if}

{if $instructions}
	<div class="alert alert-info">
		{$instructions}
	</div>
{/if}

{* Create the base form *}
{strip}
<form id="objectEditor" method="post" {if $contentType}enctype="{$contentType}"{/if} action="{$submitUrl}">
	{literal}
		<script>
			$(function(){
				$("#objectEditor").validate();
			});
		</script>
	{/literal}

	<div class="editor">
		<input type="hidden" name="objectAction" value="save">
		<input type="hidden" name="id" value='{$id}'>

		<br>

		{foreach from=$structure item=property}
			{include file="DataObjectUtil/property.tpl"}
		{/foreach}

		{* Show Recaptcha spam control if set. *}
		{if $captcha}
			{$captcha}
			{* reCAPTCHA v3 is invisible — there is no widget to submit. Instead, intercept the
			   form's submit event, obtain a token asynchronously via pikaExecuteRecaptcha(), inject
			   it as a hidden field, then submit the form programmatically.

			   Two quirks of that programmatic submit have to be worked around:
			   - The save button below is named "submit", and a form control's name shadows the
			     method of the same name on the form element, so form.submit() throws a TypeError.
			     Call the method off the prototype instead.
			   - A programmatic submit does not post the button that was clicked, but the
			     controllers gate their save logic on that button (isset($_REQUEST['submit'])),
			     so it has to be carried over as a hidden field.
			   Validation is run here as well: the native submit below bypasses the validation
			   plugin's own submit handler, so an invalid form would otherwise be posted anyway. *}
			<script>
			{literal}
			(function() {
				var awaitingToken = false;
				$('#objectEditor').on('submit', function(e) {
					if (awaitingToken) { return; }
					e.preventDefault();
					var form  = this,
					    $form = $(form);
					if (typeof $form.valid === 'function' && !$form.valid()) { return; }
					var submitter = (e.originalEvent && e.originalEvent.submitter) ||
						form.querySelector('input[type="submit"], button[type="submit"]');
					awaitingToken = true;
					pikaExecuteRecaptcha(window.pikaRecaptchaAction || 'submit', function(token) {
						/* Rebuilt on every attempt so a retry cannot post a stale token. */
						$form.find('input.recaptchaSubmitField').remove();
						$('<input type="hidden" class="recaptchaSubmitField" name="g-recaptcha-response">')
							.val(token || '').appendTo($form);
						if (submitter && submitter.name) {
							$('<input type="hidden" class="recaptchaSubmitField">')
								.attr('name', submitter.name).val(submitter.value).appendTo($form);
						}
						awaitingToken = false;
						HTMLFormElement.prototype.submit.call(form);
					});
				});
			})();
			{/literal}
			</script>
		{/if}

		{if $saveButtonText}
			<input type="submit" name="submit" value="{$saveButtonText}" class="btn btn-primary">
		{else}
			<div id="objectEditorSaveButtons">
				<input type="submit" name="submitReturnToList" value="Save Changes and Return" class="btn btn-primary">
				{if $id}
					<input type="submit" name="submitStay" value="Save Changes and Stay Here" class="btn">
				{else}
					<input type="submit" name="submitAddAnother" value="Save Changes and Add Another" class="btn">
				{/if}
			</div>
		{/if}
	</div>
</form>
{/strip}