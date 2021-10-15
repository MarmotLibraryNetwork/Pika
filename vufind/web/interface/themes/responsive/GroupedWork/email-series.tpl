{strip}
<form {*method="post" action=""*} name="popupForm" class="form-horizontal" id="emailForm">
	<div class="alert alert-info">
		<p>
			Sharing via e-mail message will send the series (with a link back to the series page) to you so you can easily find it in
			the future.
		</p>
	</div>
	<div class="form-group">
		<label for="to" class="col-sm-3">{translate text='To'}: <span class="requiredIndicator">*</span></label>
		<div class="col-sm-9">
			<input type="email" name="to" id="to" size="40" class="required email form-control">
		</div>
	</div>
	<div class="form-group">
		<label for="from" class="col-sm-3">{translate text='From'}: <span class="requiredIndicator">*</span></label>
		<div class="col-sm-9">
			<input type="email" name="from" id="from" size="40" class="required email form-control"{if $from} value="{$from}"{/if}>
		</div>
	</div>
	<div class="form-group">
		<label for="message" class="col-sm-3">{translate text='Message'}:</label>
		<div class="col-sm-9">
			<textarea name="message" id="message" rows="3" cols="40" class="form-control"></textarea>
		</div>
	</div>
    {* Show Recaptcha spam control if set. *}
    {if $captcha}
			<div class="form-group">
				<div class="col-sm-9 col-sm-offset-3">
            {$captcha}
				</div>
			</div>
    {/if}
</form>
<script type="text/javascript">
	{literal}
	$("#emailForm").validate({
		submitHandler: function(){
			Pika.GroupedWork.sendSeriesEmail("{/literal}{$id}{literal}")
		}
	});
	{/literal}
</script>
{/strip}