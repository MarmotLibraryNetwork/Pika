{strip}
	<p class="alert alert-info" id="masqueradeLoading" style="display: none">Starting Masquerade Mode</p>
	<p class="alert alert-danger" id="masqueradeAsError" style="display: none"></p>
	{*<p class="alert alert-danger" id="cookiesError" style="display: none">It appears that you do not have cookies enabled on this computer.  Cookies are required to access account information.</p>*}

<form id="masqueradeForm" class="form-horizontal"{* role="form" Assigning form role to html form tags is not neccessary *}>
	<div id="loginUsernameRow" class="form-group">
		<label for="cardNumber" class="control-label col-xs-12 col-sm-4">{translate text="Library Card Number"}:</label>
		<div class="col-xs-12 col-sm-8">
			<input type="text" name="cardNumber" id="cardNumber" value="{$cardNumber|escape}" size="28" class="form-control required" aria-required="true">
		</div>
	</div>
</form>
{/strip}
	<script>
		{literal}
		$('#cardNumber').focus();
		$("#masqueradeForm").validate({
			submitHandler: function () {
				Pika.Account.initiateMasquerade();
			}
		});
		{/literal}
	</script>
