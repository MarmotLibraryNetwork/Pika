{strip}
	<div id="page-content" class="col-xs-12">

		<h2>{translate text='Reset My PIN'}</h2>
		<div class="alert alert-info"> {*Please enter your complete card number.*} An email will be sent to the email address on file for your account containing a link to reset your {translate text='pin'}.</div>

		<form id="emailResetPin" method="POST" action="/MyAccount/EmailResetPin" class="form-horizontal">
			<div class="form-group">
				<label for="barcode" class="control-label col-xs-12 col-sm-4">{if empty($barcodeLabel)}Card Number{else}{$barcodeLabel}{/if}<span class="required">*</span></label>
				<div class="col-xs-12 col-sm-8">
					<input name="barcode" type="text" size="14" maxlength="14" class="required form-control">
				</div>
			</div>
			<div class="form-group">
				<div class="col-xs-12 col-sm-offset-4 col-sm-8">
					<input id="emailPinSubmit" name="submit" class="btn btn-primary" type="submit" value="{translate text='Reset My PIN'}">
				</div>
			</div>
		</form>
	</div>
{/strip}
<script type="text/javascript">
	{literal}
	$(function () {
		$("#emailResetPin").validate();
	});
	{/literal}
</script>
