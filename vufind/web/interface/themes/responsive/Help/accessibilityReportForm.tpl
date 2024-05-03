<div class="col-tn-12">
	<div class="alert alert-info">
		Need help with accessibility concerns? Please fill out the accessibility report form.
	</div>
	<form id="accessibilityReport" action="/Help/accessibilityReportForm" method="post">
		<input type="hidden" name="submit" value="submitted">



		<div class="form-group">
			<label for="name" class="control-label">Name: <span class="requiredIndicator">*</span></label><input type="text" name="name" id="name" class="required form-control" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="form-group">
			<label for='libraryCardNumber' class="control-label">Library Card Number: </label><input type="text" name="libraryCardNumber" id="libraryCardNumber"  maxlength="120" size="60" class="form-control">
		</div>
		<div class="form-group">
			<label for="email" class="control-label">E-mail: <span class="requiredIndicator">*</span></label><input type="text" name="email" id="email" class="required email form-control" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="form-group">
			<label for="browser" class="control-label">Browser:</label><input type="text" name="browser" id="browser" maxlength="120" size="60" class="form-control">
		</div>

		<div class="form-group">
			<label for="report" class="control-label">Please describe your issue: <span class="requiredIndicator">*</span></label><br>
			<textarea rows="10" cols="40" name="report" id="report" class="form-control required"></textarea>
		</div>
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