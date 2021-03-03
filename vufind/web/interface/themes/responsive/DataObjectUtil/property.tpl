{assign var=propName value=$property.property}
{assign var=propValue value=$object->$propName}
{if !isset($propValue) && isset($property.default)}
	{assign var=propValue value=$property.default}
{/if}
{if ((!isset($property.storeDb) || $property.storeDb == true) && !($property.type == 'oneToManyAssociation' || $property.type == 'hidden' || $property.type == 'method'))}
	<div class="form-group" id="propertyRow{$propName}">
		{* Output the label *}
		{if $property.type == 'enum'}
			<label for='{$propName}Select'{if $property.description} title="{$property.description}"{/if}>{$property.label}{if $property.required}<span class="required-input">*</span>{/if}</label>
		{elseif $property.type == 'oneToMany' && !empty($property.helpLink)}
			<div class="row">
			<div class="col-xs-11">
				<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>{$property.label}</label>
			</div>
			<div class="col-xs-1">
				<a href="{$property.helpLink}" target="_blank"><span class="glyphicon glyphicon-question-sign" title="Help" aria-hidden="true" style="color: blue;"></span></a>
			</div>
			</div>
		{elseif $property.type != 'section' && $property.type != 'checkbox'}
			{if !empty($property.helpLink)}
				<div class="row">
					<div class="col-xs-11">
						<label for='{$propName}'{if $property.description} title="{$property.description}"{/if}>{$property.label}{if $property.required}<span class="required-input">*</span>{/if}</label>
					</div>
					<div class="col-xs-1">
						<a href="{$property.helpLink}" target="_blank"><span class="glyphicon glyphicon-question-sign" title="Help" aria-hidden="true" style="color: blue;"></span></a>
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
						<h4 class="panel-title col-xs-11">
							<a data-toggle="collapse" data-parent="#accordion_{$property.label|escapeCSS}" href="#accordion_body_{$property.label|escapeCSS}">
								{$property.label}
							</a>
						</h4>
						{if $property.helpLink}
							<div class="col-xs-1">
								<a href="{$property.helpLink}" target="_blank"><span class="glyphicon glyphicon-question-sign" title="Help" aria-hidden="true" style="color: blue;"></span></a>
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
		{elseif $property.type == 'text' || $property.type == 'folder'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class='form-control {if $property.required}required{/if}'>
		{elseif $property.type == 'integer'}
			<input type="number" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.max}max="{$property.max}"{/if} {if $property.min}min="{$property.min}"{/if} {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} {if $property.step}step='{$property.step}'{/if} class='form-control {if $property.required}required{/if}'>
		{elseif $property.type == 'url'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class='form-control url {if $property.required}required{/if}'>
		{elseif $property.type == 'email'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class='form-control email {if $property.required}required{/if}'>
		{elseif $property.type == 'multiemail'}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class='form-control multiemail {if $property.required}required{/if}'>
		{elseif $property.type == 'date'}
			{*<input type='{$property.type}' name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class='form-control {if $property.required}required{/if} date'>*}
			{* disable html5 features until consistly implemented *}
			{*<input type='text' name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class='form-control {if $property.required}required{/if} date'>*}
			<input type="text" name='{$propName}' id='{$propName}' value='{$propValue}' {if $property.maxLength}maxLength='10'{/if}	class='form-control {if $property.required}required{/if} datePika'>
			{* datePika is for the form validator *}
		{elseif $property.type == 'partialDate'}
			{include file="DataObjectUtil/partialDate.tpl"}

		{elseif $property.type == 'textarea' || $property.type == 'html' || $property.type == 'crSeparated'}
			{include file="DataObjectUtil/textarea.tpl"}

		{elseif $property.type == 'password'}
			<input type="password" name='{$propName}' id='{$propName}'>

		{elseif $property.type == 'pin'}
			<input type="password" name='{$propName}' id='{$propName}' value='{$propValue|escape}' {if $property.maxLength}maxlength='{$property.maxLength}'{/if} {if $property.size}size='{$property.size}'{/if} class="form-control{if $numericOnlyPins} digits{else}{if $alphaNumericOnlyPins} alphaNumeric{/if}{/if}{if $property.required} required{/if}"{* doesn't work {if $pinMinimumLength > 0} data-rule-minlength="{$pinMinimumLength}"{/if}*}>


		{elseif $property.type == 'currency'}
			{include file="DataObjectUtil/currency.tpl"}

		{elseif $property.type == 'label'}
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
			<div class="row" >
				<div class="col-md-12 custom-file">
					<input type="file" name='{$propName}' id='{$propName}' value="{$propValue}" class="custom-file-input">
					{*<label class="custom-file-label" for='{$propName}'>Choose File</label>*}
				</div>
			</div>
			{if $propName == "cover"}
			<div class="row">
				<br />

				<div class="col-md-2"><label for="fileName" class="label-left">File Name</label></div>
				<div class="col-md-7"><input type="text" name="fileName" value="{$propValue}" class="form-control"></div>
			</div>
			{/if}
			<script>
				var prop = "#" + "{$propName}";
				var storagePath = "{$property.storagePath}";
				var sendFile = "";
				{literal}
				$( document ).ready(function() {


				$(prop).change(function(e){
					var file = e.target.files[0].name;
					var extension = file.substr((file.lastIndexOf('.') +1));


					if($("#fileName").length > 0)
						{
							var fileName = $("#fileName").val();


							if(fileName.includes("."))
								{
									sendFile = fileName;
								}
							else
							{
								sendFile = fileName + "." + extension;
							}
						}
					else
					{
						sendFile = file;
					}

					checkFile(storagePath, sendFile);
				});
	});

				function checkFile(path, file)
				{
					$(':input[type="submit"]').prop('disabled', false);
					$("#existsAlert").remove();
					$.ajax({
						url: "/Admin/AJAX?&method=fileExists&fileName=" + file + "&storagePath=" + path

					})
					.done (function(data)
					{
						if (data.exists == "true")
							{
								$("<br /><div class='row'><div class='col-md-12'><div id='existsAlert' class='alert alert-danger'>Filename Already Exists - submitting will replace an existing file. <label for='overWriteOverRide'>Overwrite: </label><input type='checkbox' id='overWriteOverRide'></div></div></div>").insertAfter(prop);

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
					<input type="checkbox" name='{$propName}' id='{$propName}' {if ($propValue == 1)}checked="checked"{/if}> {$property.label}
					{if $property.isIndexingSetting}
						&nbsp;<span class="glyphicon glyphicon-time" aria-hidden="true" title="This setting is a change to indexing"></span>
					{/if}
				</label>
			</div>

		{elseif $property.type == 'oneToMany'}
			{include file="DataObjectUtil/oneToMany.tpl"}
		{/if}

	</div>
{elseif $property.type == 'hidden'}
	<input type="hidden" name='{$propName}' value='{$propValue}'>
{/if}

{if $property.showDescription}
	<div class="propertyDescription">{$property.description}</div>
{/if}