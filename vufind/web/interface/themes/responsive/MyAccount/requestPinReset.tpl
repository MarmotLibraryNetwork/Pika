<div id="page-content" class="col-xs-12">

	<h2>{translate text='Forgot Your PIN?'}</h2>
	<div class="alert alert-info">Enter your card number. We will send a PIN reset link to the email address we have on file.</div>

	<form id="emailPin" method="POST" action="/MyAccount/RequestPinReset" class="form-horizontal">
		<div class="form-group">
			<label for="barcode" class='control-label col-xs-12 col-sm-4'>Card Number<span class="required">*</span></label>
			<div class='col-xs-12 col-sm-8'>
				<input name="barcode" type="text" size="14" maxlength="14" class="required form-control"/>
			</div>
		</div>
		<div class="form-group">
			<div class='col-xs-12 col-sm-offset-4 col-sm-8'>
				<input id="requestPinResetSubmit" name="submit" class="btn btn-primary"  type="submit" value="Request PIN Reset"/>
			</div>
		</div>
	</form>
</div>
<script type="text/javascript">
	{literal}
	$(document).ready(function () {
		$("#emailPin").validate();
	});
	{/literal}
</script>
