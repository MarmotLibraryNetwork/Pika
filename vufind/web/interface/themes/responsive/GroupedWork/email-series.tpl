{strip}
<form {*method="post" action=""*} name="popupForm" class="form-horizontal" id="emailForm">
	<div class="alert alert-info">
		<p>
			Sharing via e-mail message will send the series (with a link back to the series page) to you so you can easily find it in the future.
		</p>
	</div>
	<div class="form-group">
		<label for="to" class="col-md-3">{translate text='To'}: <span class="required-input">*</span></label>
		<div class="col-md-9">
			<input type="email" name="to" id="to" size="40" class="required email form-control" aria-required="true">
		</div>
	</div>
	<div class="form-group">
		<label for="from" class="col-md-3">{translate text='From'}: <span class="required-input">*</span></label>
		<div class="col-md-9">
			<input type="email" name="from" id="from" size="40" class="required email form-control" aria-required="true" {if $from} value="{$from}"{/if}>
		</div>
	</div>
	<div class="form-group">
		<label for="message" class="col-md-3">{translate text='Message'}:</label>
		<div class="col-md-9">
			<textarea name="message" id="message" rows="3" cols="40" class="form-control"></textarea>
		</div>
	</div>
    {* Show Recaptcha spam control if set. *}
    {if $captcha}
			<div class="form-group">
				<div class="col-md-9 offset-md-3">
            {$captcha}
				</div>
			</div>
    {/if}
</form>
<script>
	{literal}
	$("#emailForm").validate({
		submitHandler: function(){
			Pika.GroupedWork.sendSeriesEmail("{/literal}{$id}{literal}")
		}
	});
	{/literal}
</script>
{/strip}