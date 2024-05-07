{strip}
<div align="left">
	{if $message}<div class="error">{$message|translate}</div>{/if}


	<form id="emailListForm" class="form form-horizontal">
		<div class="form-group">
			<input type="hidden" name="listId" value="{$listId|escape}">
			<label for="to" class="control-label col-xs-2">{translate text='To'} <span class="required-input">*</span></label>
			<div class="col-xs-10">
				<input type="text" name="to" id="to" size="40" class="required email form-control" aria-required="true">
			</div>
		</div>
		<div class="form-group">
			<label for="from" class="control-label col-xs-2">{translate text='From'} <span class="required-input">*</span></label>
			<div class="col-xs-10">
				<input type="text" name="from" id="from" size="40" class="required email form-control" aria-required="true"{if $from} value="{$from}"{/if}>
			</div>
		</div>
		<div class="form-group">
			<label for="message" class="control-label col-xs-2">{translate text='Message'}</label>
			<div class="col-xs-10">
				<textarea name="message" id="message" rows="3" cols="40" class="form-control"></textarea>
			</div>
		</div>
      {* Show Recaptcha spam control if set. *}
      {if $captcha}
				<div class="form-group">
					<div class="col-xs-10 col-xs-offset-2">
              {$captcha}
					</div>
				</div>
      {/if}
	</form>
</div>
{/strip}
<script>
	{literal}
	$("#emailListForm").validate({
		submitHandler: function(){
			Pika.Lists.SendMyListEmail();
		}
	});
	{/literal}
</script>