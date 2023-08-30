{strip}
	<div id="page-content" class="col-xs-12">

		<h2>{translate text='Reset My PIN'}</h2>
		<div class="alert alert-info"> {*Please enter your complete card number.*} Click the "{translate text='Reset My PIN'}" button to receive an email to the email address on file containing a link to reset your {translate text='pin'}.</div>
		{*Reference to card number is removed to avoid confusion on login form labels. See D-4417 *}

		<form id="emailResetPin" method="POST" action="/MyAccount/EmailResetPin" class="form-horizontal">
			<div class="form-group">
				<label for="barcode" class="control-label col-xs-12 col-sm-4">{if empty($barcodeLabel)}Card Number{else}{$barcodeLabel}{/if}<span class="required">*</span></label>
				<div class="col-xs-12 col-sm-8">
					<input id="barcode" name="barcode" type="text" size="14" maxlength="14" class="required form-control">
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
