<h1 id="pageTitle" role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
<div class="col-12">
	<div class="alert alert-info">
		Need help with accessibility concerns? Please fill out the accessibility report form. All fields marked <span class="required-input">*</span> are required.
	</div>
	<form id="accessibilityReport" action="/Help/accessibilityReportForm" method="post">
		<input type="hidden" name="submit" value="submitted">

		<div class="mb-3">
			<label for="name" class="form-label">Name: <span class="required-input">*</span></label><input type="text" name="name" aria-required="true" id="name" class="required form-control" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="mb-3">
			<label for='libraryCardNumber' class="form-label">Library Card Number: </label><input type="text" name="libraryCardNumber" id="libraryCardNumber"  maxlength="120" size="60" class="form-control">
		</div>
		<div class="mb-3">
			<label for="email" class="form-label">E-mail: <span class="required-input">*</span></label><input type="text" name="email" id="email" aria-required="true" class="required email form-control" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="mb-3">
			<label for="browser" class="form-label">Browser:</label><input type="text" name="browser" id="browser" maxlength="120" size="60" class="form-control">
		</div>

		<div class="mb-3">
			<label for="report" class="form-label">Please describe your web accessibility issue: <span class="required-input">*</span></label><br>
			<textarea rows="10" cols="40" name="report" id="report" aria-required="true" class="form-control required"></textarea>
		</div>
      {if $captcha}
				<div class="row mb-3">
					<div class="col-md-9 offset-md-3">
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
				<div class="mb-3">
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