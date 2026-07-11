<form action="#" method="post" class="form form-horizontal" id="emailSearchForm">
	<div class="row mb-3">
		<label for="to" class="col-md-3">{translate text='To'}: <span class="required-input">*</span></label>
		<div class="col-md-9">
			<input type="email" name="to" id="to" size="40" class="required email form-control" aria-required="true">
		</div>
	</div>
	<div class="row mb-3">
		<label for="from" class="col-md-3">{translate text='From'}: <span class="required-input">*</span></label>
		<div class="col-md-9">
			<input type="email" name="from" id="from" size="40" class="required email form-control" aria-required="true"{if $from} value="{$from}"{/if}>
		</div>
	</div>
	<div class="row mb-3">
		<label for="message" class="col-md-3">{translate text='Message'}:</label>
		<div class="col-md-9">
			<textarea name="message" id="message" rows="3" cols="40" class="form-control"></textarea>
		</div>
	</div>
    {* Show Recaptcha spam control if set. *}
    {if $captcha}
			<div class="row mb-3">
				<div class="col-md-9 offset-md-3">
            {$captcha}
				</div>
			</div>
    {/if}
</form>

<script>
	{literal}
	$("#emailSearchForm").validate({
		submitHandler: function(){
			Pika.Searches.sendEmail();
		}
	});
	{/literal}
</script>