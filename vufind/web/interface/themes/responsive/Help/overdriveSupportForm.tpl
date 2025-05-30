<h1 id="pageTitle" role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
<div class="col-tn-12">
	<div class="alert alert-info">
		Need help downloading a title or using the title on your device?  Please fill out this support form.
	</div>
	<form id="overdriveSupport" action="/Help/OverDriveSupport" method="post">
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
			<label for="title" class="control-label">Book Title/Author:</label><input type="text" name="title" id="title" maxlength="120" size="60" class="form-control" value="{$title}">
		</div>
		<div class="form-group">
			<label for="device" class="control-label">Device:</label><input type="text" name="device" id="device"{if $deviceName} value="{$deviceName}"{/if} maxlength="120" size="60" class="form-control">
		</div>
		<div class="form-group">
			<label for="format" class="control-label">Format:</label>
			<select id="format" name="format" class="form-control">
				{if empty($formats) || count($formats) > 1}
					{* Only show the default option if there are multiples to select. Or no formats set. *}
					<option value="na">-Select a Format-</option>
				{/if}
				{foreach from="$formats" item="formatName" key="formatTextId"}
					<option value="{$formatTextId}">{$formatName}</option>
					{foreachelse}
					<option value="ePub">Adobe E-pub eBook</option>
					<option value="kindle">Kindle eBook</option>
					<option value="magazine">E-magazine</option>
					<option value="mp3">MP3 Audio Book</option>
					<option value="video">Video</option>
					<option value="Unknown">N/A or Unknown</option>
				{/foreach}
			</select>
		</div>
		<div class="form-group">
			<label for="operatingSystem" class="control-label">Operating System:</label>
			<select name="operatingSystem" id="operatingSystem" class="form-control">
				<option value="">-Select an Operating System-</option>
				<option value="Win-11">Windows 11</option>
				<option value="Win-10">Windows 10</option>
				<option value="Win-8">Windows 8</option>
				{*
								<option value="Win-7">Windows 7</option>
								<option value="XP">Windows XP</option>
								<option value="Vista">Windows Vista</option>
				*}
				<option value="Mac">Mac OS</option>
				<option value="kindle">Kindle</option>
				<option value="Linux">Linux/Unix</option>
				<option value="Android">Android</option>
				<option value="IOS">iPhone/iPad/iPod</option>
				<option value="other">Other - Please specify Below</option>
			</select>
		</div>
		<div class="form-group">
			<label for="problem" class="control-label">Please describe your issue: <span class="required-input">*</span></label><br>
			<textarea rows="10" cols="40" name="problem" id="problem" class="form-control required"></textarea>
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
				<button class="btn btn-sm btn-primary" onclick='return $("#overdriveSupport").validate()'>Submit</button>
			</div>
		{/if}
	</form>
</div>
{literal}
	<script>
		$(function(){
			var supportForm = $("#overdriveSupport");
			supportForm.validate({
				submitHandler: function () {
					Pika.submitOverDriveForm();
				}
			});
		});
	</script>
{/literal}