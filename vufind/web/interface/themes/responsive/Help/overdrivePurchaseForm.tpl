<h1 id="pageTitle" role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
<div class="col-tn-12">
	<div class="alert alert-info">
		Can't find what you are looking for in OverDrive? Please fill out this form to suggest a purchase.
	</div>
	<form id="overdrivePurchaseRequest" action="/Help/OverDrivePurchaseRequest" method="post">
		<input type="hidden" name="submit" value="submitted">

		{if !$loggedIn}
			<div class="form-group">
				<label for='libraryCardNumber' class="control-label">Library Card Number:  <span class="required-input">*</span></label>
				<input type="text" name="libraryCardNumber" id="libraryCardNumber" class="required form-control" aria-required="true" maxlength="20" size="20">
			</div>
		{else}
			<div class="form-group">
				<label for="libraryCardNumber" class="control-label">Library Card Number: <span class="required-input">*</span></label>
				<input type="text" name="libraryCardNumber" id="libraryCardNumber" class="required form-control" aria-required="true" maxlength="20" disabled="true" aria-disabled="true" size="20" value="{$user->barcode}">
				<input type="hidden" name="homeLibrary" value="{$user->homeLocation}">
			</div>
		{/if}
		<div class="form-group">
			<label for="name" class="control-label">Name: <span class="required-input">*</span></label><input type="text" name="name" id="name" class="required form-control" aria-required="true" maxlength="120" size="60" value="{$name}">
		</div>
		<div class="form-group">
			<label for="email" class="control-label">E-mail: <span class="required-input">*</span></label><input type="text" name="email" id="email" class="required email form-control" aria-required="true" maxlength="120" size="60" value="{$email}">
		</div>
		<div class="form-group">
			<label for="title" class="control-label">Book Title: <span class="required-input">*</span></label><input type="text" name="title" id="title" maxlength="120" size="60" class="required form-control" aria-required="true" value="{$title}">
		</div>
		<div class="form-group">
			<label for="author" class="control-label">Author: <span class="required-input">*</span></label><input type="text" name="author" id="author" maxlength="120" size="60" class="required form-control" aria-required="true" value="{$author}">
		</div>
		<div class="form-group">
			<label for="format" class="control-label">Format: <span class="required-input">*</span></label>
			<select id="format" name="format" class="form-control required" aria-required="true">
				{if empty($formats) || count($formats) > 1}
					{* Only show the default option if there are multiples to select. Or no formats set. *}
					<option value="na">-Select a Format-</option>
				{/if}
				{foreach from="$formats" item="formatName" key="formatTextId"}
					<option value="{$formatTextId}">{$formatName}</option>
					{foreachelse}
					<option value="audiobook">Audiobook</option>
					<option value="ebook">eBook</option>
					<option value="emagazine">eMagazine</option>
					<option value="any">Any Format</option>
				{/foreach}
			</select>
		</div>
		<div class="form-group">
			<label for="comments" class="control-label">Any Comments?: </label><br>
			<textarea rows="10" cols="40" name="comments" id="comments" class="form-control"></textarea>
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
				<button class="btn btn-sm btn-primary" onclick='return $("#overdrivePurchaseRequest").validate()'>Submit</button>
			</div>
		{/if}
	</form>
</div>
{literal}
	<script>
		$(function(){
			var supportForm = $("#overdrivePurchaseRequest");
			supportForm.validate({
				submitHandler: function () {
					Pika.submitOverDrivePurchaseRequest();
				}
			});
		});
	</script>
{/literal}