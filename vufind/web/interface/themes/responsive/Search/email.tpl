<form action="#" method="post" class="form form-horizontal" id="emailSearchForm">
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
</form>

<script type="text/javascript">
	{literal}
	$("#emailSearchForm").validate({
		submitHandler: function(){
			VuFind.Searches.sendEmail();
		}
	});
	{/literal}
</script>