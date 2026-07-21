{strip}
	<form id="bookClubKitRequestForm">
		<input type="hidden" name="submit" value="submitted">
		<input type="hidden" name="homeLibraryId" value="{$homeLibraryId}">
		<input type="hidden" name="recordId" value="{$recordId}">
		<div class="form-group">
			<label for="libraryCardNumber" class="control-label">Library Card Number:</label>
			<input type="text" name="libraryCardNumber" id="libraryCardNumber" class="form-control" maxlength="20" size="20" disabled="disabled" aria-disabled="true" value="{$libraryCardNumber}">
		</div>
		<div class="form-group">
			<label for="name" class="control-label">Name: <span class="required-input">*</span></label>
			<input type="text" name="name" id="name" class="required form-control" aria-required="true" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="form-group">
			<label for="email" class="control-label">E-mail: <span class="required-input">*</span></label>
			<input type="text" name="email" id="email" class="required email form-control" aria-required="true" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="form-group">
			<label for="title" class="control-label">Title:</label>
			<input type="text" name="title" id="title" class="form-control" maxlength="255" size="60" disabled="disabled" aria-disabled="true" value="{$title}">
		</div>
	</form>
{/strip}
{literal}
	<script>
		$(function(){
			$('#bookClubKitRequestForm').validate({
				submitHandler: function(){
					Pika.Record.submitBookClubKitRequestForm();
				}
			});
		});
	</script>
{/literal}
