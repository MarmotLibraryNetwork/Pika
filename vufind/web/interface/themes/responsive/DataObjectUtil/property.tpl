{assign var=propName value=$property.property}
{assign var=propValue value=$object->$propName}
{if !isset($propValue) && isset($property.default)}
	{assign var=propValue value=$property.default}
{/if}
{if ((!isset($property.storeDb) || $property.storeDb == true) && !($property.type == 'oneToManyAssociation' || $property.type == 'hidden' || $property.type == 'method'))}
	<div class="form-group" id="propertyRow{$propName}">
		{* Output the label *}
		{if $property.type == 'enum'}
			<label for='{$propName}Select'{if $property.description} title="{$property.description}"{/if}>{$property.label}
				{if $property.required}<span class="required-input">*</span>{/if}
				{if $property.isIndexingSetting}
					&nbsp;<span class="glyphicon glyphicon-time" aria-hidden="true" title="This setting is a change to indexing"></span>
				{/if}
			</label>
		{elseif $property.type == 'oneToMany' && !empty($property.helpLink)}
			<div class="row">
			<div class="col-xs-11">
				<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>{$property.label}</label>
					{if $property.isIndexingSetting}
						&nbsp;<span class="glyphicon glyphicon-time" title="This setting is a change to indexing"></span>
					{/if}
			</div>
			<div class="col-xs-1">
				<a href="{$property.helpLink}" aria-label="Help Link" target="_blank"><span class="help-icon glyphicon glyphicon-question-sign" title="Help" aria-hidden="true"></span></a>
			</div>
			</div>
		{elseif $property.type != 'section' && $property.type != 'checkbox' && $property.type != 'checkboxWarn' && $property.type != 'header'}
			{if !empty($property.helpLink)}
				<div class="row">
					<div class="col-xs-11">
						<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>{$property.label}{if $property.required}<span class="required-input">*</span>{/if}</label>
					</div>
					<div class="col-xs-1">
						<a href="{$property.helpLink}" aria-label="Help Link" target="_blank"><span class="help-icon glyphicon glyphicon-question-sign" title="Help" aria-hidden="true"></span></a>
					</div>
				</div>
			{else}
				<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>
					{$property.label}
					{if $property.required}<span class="required-input">*</span>{/if}
					{if $property.isIndexingSetting}
						&nbsp;<span class="glyphicon glyphicon-time" aria-hidden="true" title="This setting is a change to indexing"></span>
					{/if}
				</label>
			{/if}
		{/if}
		{* Output the editing control*}
		{if $property.type == 'section'}
			<div class="panel-group" id="accordion_{$property.label|escapeCSS}">
				<div class="panel panel-default">
					<div class="panel-heading row">
						<div class="panel-title col-tn-11">
							<a data-toggle="collapse" data-parent="#accordion_{$property.label|escapeCSS}" href="#accordion_body_{$property.label|escapeCSS}">
								{$property.label}
							</a>
						</div>
						{if $property.helpLink}
							<div class="col-tn-1">
								<a href="{$property.helpLink}" aria-label="Help Link" target="_blank"><span class="help-icon glyphicon glyphicon-question-sign" title="Help" aria-hidden="true"></span></a>
							</div>
						{/if}
					</div>

					<div id="accordion_body_{$property.label|escapeCSS}" class="panel-collapse {if $property.open}active{else}collapse{/if}">
						<div class="panel-body">
							{if $property.instructions}
								<div class="alert alert-info">
									{$property.instructions}
								</div>
							{/if}
							{foreach from=$property.properties item=property}
								{include file="DataObjectUtil/property.tpl"}
							{/foreach}
						</div>
					</div>
				</div>
			</div>
		{elseif $property.type == 'tel'}
			<input type="tel" name='{$propName}' id='{$propName}' value='{$propValue|escape}' pattern="[0-9]{ldelim}3{rdelim}-[0-9]{ldelim}3{rdelim}-[0-9]{ldelim}4{rdelim}" {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control {if $property.required}required{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
    {elseif $property.type == 'text' || $property.type == 'folder'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control {if $property.required}required{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
		{elseif $property.type == 'integer'}
			<input type="number" name='{$propName}' id='{$propName}' value='{$propValue|escape}'{if isset($property.max)} max="{$property.max}"{/if}{if isset($property.min)} min="{$property.min}"{/if}{if $property.maxLength} maxlength='{$property.maxLength}'{/if}{if $property.size} size='{$property.size}'{/if}{if $property.step} step='{$property.step}'{/if} class="form-control{if $property.required} required{/if}{if isset($property.min)} minimum{/if}{if isset($property.max)} maximum{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
				{*use isset() with property.min because it can be set to zero, which cause a false in if block*}
		{elseif $property.type == 'url'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control url {if $property.required}required{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
		{elseif $property.type == 'email'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control email {if $property.required}required{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
		{elseif $property.type == 'multiemail'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control multiemail {if $property.required}required{/if}"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
		{elseif $property.type == 'date'}
			{*<input type='{$property.type}' name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class="form-control {if $property.required}required{/if} date"{if $property.required} aria-required="true"{/if}>*}
			{* disable html5 features until consistly implemented *}
			{*<input type='text' name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class="form-control {if $property.required}required{/if} date"{if $property.required} aria-required="true"{/if}>*}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class="form-control {if $property.required}required{/if} datePika"{if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
			{* datePika is for the form validator *}
		{elseif $property.type == 'dateReadOnly'}
			{if !empty($propValue)}
				<div id="{$propName}">{$propValue|date_format:"%b %d, %Y %r"}</div>
			{/if}
		{elseif $property.type == 'partialDate'}
			{include file="DataObjectUtil/partialDate.tpl"}

		{elseif $property.type == 'textarea' || $property.type == 'html' || $property.type == 'crSeparated'}
			{include file="DataObjectUtil/textarea.tpl"}

		{elseif $property.type == 'password'}
			<input type="password" name='{$propName}' id='{$propName}'{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>

		{elseif $property.type == 'pin'}
			<input type="password" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control{if $numericOnlyPins} digits{elseif $alphaNumericOnlyPins} alphaNumeric{/if}{if $property.required} required{/if}"{* doesn't work {if $pinMinimumLength > 0} data-rule-minlength="{$pinMinimumLength}"{/if}*} {if $property.required} aria-required="true"{/if}{if $property.autocomplete} autocomplete="{$property.autocomplete}"{/if}>
			{if $property.showPasswordRequirements}
			<br>
			<div class="alert alert-info">
				{include file="MyAccount/passwordRequirements.tpl"}
			</div>
		{/if}

		{elseif $property.type == 'header'}
			<h2 id="{$propName}"{if $property.class} class="{$property.class}"{/if}>{$property.value|escape}</h2>
		
		{elseif $property.type == 'currency'}
			{include file="DataObjectUtil/currency.tpl"}

		{elseif $property.type == 'label' || $property.type == 'readOnly'}
			<div id='{$propName}'>{$propValue}</div>

		{elseif $property.type == 'enum'}
			{include file="DataObjectUtil/enum.tpl"}

		{elseif $property.type == 'multiSelect'}
			{include file="DataObjectUtil/multiSelect.tpl"}

		{elseif $property.type == 'image' || $property.type == 'file'}
			{if $propValue}
				{if $property.type == 'image'}
						<br>
						{if $property.storagePath}
							<figure>
								<img src="{$object->getImageUrl('large')}" alt="{$propValue}" style="max-width:175px;height:auto;">
								<figcaption>{$propValue}</figcaption>
							</figure>
						{else}
							<figure>
								<img src='/files/thumbnail/{$propValue}' alt="{$propValue}">
								<figcaption>{$propValue}</figcaption>
							</figure>
						{/if}

			{if $propName != "cover"}
			<div class="checkbox" ><label for="remove{$propName}">Remove {$propName}<input type="checkbox"  name='remove{$propName}' id='remove{$propName}'></label></div>

			{/if}
					<input type="hidden" name="currentName" id="currentName" value='{$propValue|escape}'>
					<br>
				{else}
					Existing file: {$propValue}
					<input type="hidden" name='{$propName}_existing' id='{$propName}_existing' value='{$propValue|escape}'>

				{/if}
		{/if}

			{* Display a table of the association with the ability to add and edit new values *}
			<div class="row">
				<div class="col-md-12 custom-file">
					<input type="file" name='{$propName}' id='{$propName}' value="{$propValue}" class="custom-file-input">
					{*<label class="custom-file-label" for='{$propName}'>Choose File</label>*}
				</div>
			</div>
			{if $propName == "cover"}
			<div class="row">
				<br>

				<div class="col-md-2"><label for="fileName" class="label-left">File Name</label></div>
				<div class="col-md-7"><input type="text" id="fileName" name="fileName" value="{$propValue}" class="form-control"></div>

			</div>
			{/if}
			<script>
				var prop = "#" + "{$propName}";
				var storagePath = "{$property.storagePath}";
				var sendFile = "";
				{literal}
				$(function() {
					$(prop).change(function(e){
						var file = e.target.files[0].name;
						var extension = file.substr((file.lastIndexOf('.') +1));

						if(this.files[0].size > 1900000){
							$(':input[type="submit"]').prop('disabled', true);
							$(".custom-file").append("<div class='alert alert-danger' id='sizeWarning'>The image is too large and upload will fail. Please resize and try again. Images must be under 1.8MB</div>");
						}else{
							$(':input[type="submit"]').prop('disabled', false);
							$("#sizeWarning").remove();
						}

						if ($("#fileName").length > 0) {
							var fileName = $("#fileName").val();
							sendFile = fileName.includes(".") ? fileName : fileName + "." + extension;
						} else {
							sendFile = file;
						}

						checkFile(storagePath, sendFile);
					});
				});

				function checkFile(path, file) {
					$(':input[type="submit"]').prop('disabled', false);
					$("#existsAlert").remove();
					$.ajax({
						url: "/Admin/AJAX?&method=fileExists&fileName=" + file + "&storagePath=" + path
					})
						.done(function (data) {
							if (data.exists == "true") {
								$("<br><div class='row'><div class='col-md-12'><div id='existsAlert' class='alert alert-danger'>Filename Already Exists - submitting will replace an existing file. <label for='overWriteOverRide'>Overwrite: </label><input type='checkbox' id='overWriteOverRide'></div></div></div>").insertAfter(prop);

								$(':input[type="submit"]').prop('disabled', true);
								$("#overWriteOverRide").change(function() {
									if (this.checked) {
										$(':input[type="submit"]').prop('disabled', false);
									} else
									{
										$(':input[type="submit"]').prop('disabled', true);
									}
								});
							}
					})
					return false;
				}
				{/literal}
			</script>
		{elseif $property.type == 'checkbox'}
			<div class="checkbox">
				<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>
					<input type="checkbox" name='{$propName}' id='{$propName}' {if ($propValue == 1)}checked="checked"{/if}> {if $property.boldTheLabel}<strong>{/if}{$property.label}{if $property.boldTheLabel}</strong>{/if}
					{if $property.isIndexingSetting}
						&nbsp;<span class="glyphicon glyphicon-time" aria-hidden="true" title="This setting is a change to indexing"></span>
					{/if}
				</label>
			</div>
			{if isset($property.warning)}
			<script>
				$('#{$propName}').on('click', function(d){ldelim}
					varSelectorId = '#'+this.id;
					if ($(varSelectorId).is(":checked")){ldelim}
						$(varSelectorId).prop('checked',false);
						Pika.confirm("{$property.warning}. This cannot be undone. Please make sure you are aware of the risks before saving", function(){ldelim}
							$(varSelectorId).prop('checked',true);
							$('.modal-footer button.btn-default').click();
                {rdelim});
              {rdelim}
            {rdelim});
			</script>
		{/if}
		{elseif $property.type == 'oneToMany'}
			{include file="DataObjectUtil/oneToMany.tpl"}
		{/if}

	</div>
{elseif $property.type == 'hidden'}
	<input type="hidden" name='{$propName}' value='{$propValue}'>
{/if}

{if $property.showDescription}
	<div class="well well-sm">{$property.description}</div>
{/if}