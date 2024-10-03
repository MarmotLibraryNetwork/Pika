{strip}
<h1 role="heading" aria-level="1" class="h2">{translate text='Register for a Library Card'}</h1>
<div class="page">
		{if (isset($selfRegResult) && $selfRegResult.success)}
			<div id="selfRegSuccess" class="alert alert-success">
				{if $selfRegistrationSuccessMessage}
					{$selfRegistrationSuccessMessage}
				{else}
					Congratulations, you have successfully registered for a new library card.&nbsp;
					You will have limited privileges.<br>
					Please bring a valid ID to the library to receive a physical library card.
				{/if}
			</div>
			<div class="alert alert-info">
				Your library card number is <strong>{$selfRegResult.barcode}</strong>.
			</div>
		{else}
			{img_assign filename='self_reg_banner.png' var=selfRegBanner}
			{if $selfRegBanner}
				<img src="{$selfRegBanner}" alt="Self Register for a new library card" class="img-responsive">
			{/if}

			<div id="selfRegDescription" class="alert alert-info">
				{if $selfRegistrationFormMessage}
					{$selfRegistrationFormMessage}
				{else}
					This page allows you to register as a patron of our library online. You will have limited privileges initially.
				{/if}
			</div>
			{if (isset($selfRegResult))}
				{if !$selfRegResult.success && !empty($selfRegResult.message)}
					<div id="selfRegFail" class="alert alert-warning">
						{$selfRegResult.message}
					</div>
				{else}
					<div id="selfRegFail" class="alert alert-warning">
						Sorry, we were unable to create a library card for you.  You may already have an account or there may be an error with the information you entered.
						&nbsp;Please try again or visit the library in person (with a valid ID) so we can create a card for you.
					</div>
				{/if}
			{/if}
			{if $captchaMessage}
				<div id="selfRegFail" class="alert alert-warning">
				{$captchaMessage}
				</div>
			{/if}
			<div id="selfRegistrationFormContainer">
				{$selfRegForm}
			</div>
		{/if}
</div>
{/strip}
<script>
	{if $promptForBirthDateInSelfReg}

	{literal}
	$(function(){
		$('input.datePika').datepicker({
			format: "mm-dd-yyyy"
			,endDate: "+0d"
			,startView: 2
		});
		{/literal}
		{*  Guardian Name is required for users under 18 for Sacramento Public Library *}
		{literal}
		if ($('#guardianFirstName').length){

			$('#birthdate').focusout(function(){
				var birthDate = $(this).datepicker('getDate');
				if (birthDate) {
					var today = new Date(),
							age = today.getFullYear() - birthDate.getFullYear();
					if (today.getMonth() < birthDate.getMonth() ||
							(today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) {
						age--;
					}
					var isMinor = age < 18;
					/* Have to add/remove rule to each element separately, can't combine selector */
					$("#guardianFirstName").rules("add", {
						required:isMinor
					});
					$("#guardianLastName").rules("add", {
						required:isMinor
					});
					if (isMinor){
						if ( $('#propertyRowguardianFirstName label span.required-input').length === 0) {
							$('#propertyRowguardianFirstName label').append('<span class="required-input">*</span>');
							$("#guardianFirstName").attr('aria-required', true);
						}
						$('#propertyRowguardianFirstName, #propertyRowguardianLastName').show();
						if ( $('#propertyRowguardianLastName label span.required-input').length === 0) {
							$('#propertyRowguardianLastName label').append('<span class="required-input">*</span>');
							$("#guardianLastName").attr('aria-required', true);
						}
					} else {
						$('#propertyRowguardianFirstName, #propertyRowguardianLastName').hide();
						$("#guardianFirstName,#guardianFirstName").attr('required-input', false);
						$('#propertyRowguardianFirstName label, #propertyRowguardianLastName label').children('span.required-input').remove();
					}
				}
			});
		}
		{/literal}

		{*  Guardian Name is required for users under 16 for Broomfield Public Library *}
		{literal}
		if ($('#guardianName').length){

			$('#birthdate').focusout(function(){
				var birthDate = $(this).datepicker('getDate');
				if (birthDate) {
					var today = new Date(),
									age = today.getFullYear() - birthDate.getFullYear();
					if (today.getMonth() < birthDate.getMonth() ||
									(today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) {
						age--;
					}
					var isMinor = age < 16;
					$("#guardianName").rules("add", {
						required:isMinor
					});
					if (isMinor){
						if ( $('#propertyRowguardianName label span.required-input').length === 0) {
							$('#propertyRowguardianName label').append('<span class="required-input">*</span>');
							$("#guardianName").attr('aria-required', true);
						}
					} else {
						//$('#propertyRowguardianName').hide();
						$('#propertyRowguardianName label').children('span.required-input').remove();
						$("#guardianName").attr('aria-required', false);
					}
				}
			});
		}

	});
	{/literal}
	{/if}
	{* Pin Validation for CarlX, Sirsi, MLN1, MLN2, and Sacramento *}
	{literal}
	$(function(){
		$('#zip').rules('add', {zipcodeUS:true});
		$('#pin').rules('add', {minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}4{/if}{literal}});
		$('#pin').rules('add', {maxlength:{/literal}{if $pinMaximumLength}{$pinMaximumLength}{else}30{/if}{literal}});
		$('#pin1').rules('add', {equalTo: "#pin",minlength:{/literal}{if $pinMinimumLength}{$pinMinimumLength}{else}0{/if}{literal}});
		{/literal}
		{if $selfRegStateRegex && $selfRegStateMessage}
		{* Add state validation *}
		jQuery.validator.addMethod("stateCheck", function(value, element) {ldelim}
			return this.optional( element ) || {$selfRegStateRegex}.test( value );
			{rdelim}, '{$selfRegStateMessage}');
		$('#state').rules('add', {ldelim}stateCheck:true{rdelim});
		{/if}
		{if $selfRegZipRegex && $selfRegZipMessage}
		{* Add additional/specific zip code validation *}
		jQuery.validator.addMethod("zipCheck", function(value, element) {ldelim}
			return this.optional( element ) || {$selfRegZipRegex}.test( value );
			{rdelim}, '{$selfRegZipMessage}');
		$('#zip').rules('add', {ldelim}zipCheck:true{rdelim});
		{/if}
		{literal}
	});
	{/literal}
</script>
