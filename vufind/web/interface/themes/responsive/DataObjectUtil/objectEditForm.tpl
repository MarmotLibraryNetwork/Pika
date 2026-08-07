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
			   it as a hidden field, then re-submit using the native DOM method (bypasses jQuery's
			   submit handler to avoid an infinite loop). *}
			<script>
			{literal}
			(function() {
				var submitted = false;
				$('#objectEditor').on('submit', function(e) {
					if (!submitted) {
						e.preventDefault();
						submitted = true;
						var form = this;
						pikaExecuteRecaptcha(window.pikaRecaptchaAction || 'submit', function(token) {
							$('<input type="hidden" name="g-recaptcha-response">').val(token).appendTo(form);
							form.submit();
						});
					}
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