<h1 id="pageTitle" role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
<div class="col-tn-12">
	<div class="alert alert-info">
		Need help with accessibility concerns? Please fill out the accessibility report form.
	</div>
	<form id="accessibilityReport" action="/Help/accessibilityReportForm" method="post">
		<input type="hidden" name="submit" value="submitted">



		<div class="form-group">
			<label for="name" class="control-label">Name: <span class="required-input">*</span></label><input type="text" name="name" aria-required="true" id="name" class="required form-control" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="form-group">
			<label for='libraryCardNumber' class="control-label">Library Card Number: </label><input type="text" name="libraryCardNumber" id="libraryCardNumber"  maxlength="120" size="60" class="form-control">
		</div>
		<div class="form-group">
			<label for="email" class="control-label">E-mail: <span class="required-input">*</span></label><input type="text" name="email" id="email" aria-required="true" class="required email form-control" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="form-group">
			<label for="browser" class="control-label">Browser:</label><input type="text" name="browser" id="browser" maxlength="120" size="60" class="form-control">
		</div>

		<div class="form-group">
			<label for="report" class="control-label">Please describe your issue: <span class="required-input">*</span></label><br>
			<textarea rows="10" cols="40" name="report" id="report" aria-required="true" class="form-control required"></textarea>
		</div>
      {if $captcha}
				<div class="form-group">
					<div class="col-sm-9 col-sm-offset-3">
              {$captcha}
					</div>
				</div>
      {/if}
      {if $captchaMessage}
				<div class="alert alert-warning">
            {$captchaMessage}
				</div>
      {/if}
      {if $lightbox == false}
				<div class="form-group">
					<button class="btn btn-sm btn-primary" onclick='return $("#accessibilityReport").validate()'>Submit</button>
				</div>
      {/if}
	</form>
</div>
{literal}
	<script>
		$(function(){
			var supportForm = $("#accessibilityReport");
			supportForm.validate({
				submitHandler: function () {
						Pika.submitAccessibilityReport();
				}
			});
		});
	</script>
{/literal}