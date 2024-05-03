{strip}
<div id="main-content" class="col-tn-12 col-xs-12">
	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{/if}
	<form name="addAdministrator" method="post" enctype="multipart/form-data" class="form-horizontal">
		<fieldset>
			<legend><h1 role="heading" aria-level="1" class="h2">Add a New Administrator</h1></legend>
			<input type="hidden" name="objectAction" value="processNewAdministrator">
			<div class="row form-group">
				<label for="barcode" class="col-sm-2 control-label">Barcode: </label>
				<div class="col-sm-10">
					<input type="text" name="barcode" id="barcode" class="form-control"{if $barcode} value="{$barcode}"{/if}>
				</div>
			</div>
			<div class="alert alert-info">
				<p>Enter the barcode for the user who should be given administration privileges.</p>
				<div class="alert alert-warning">
				<ul>
					<li>The user must have logged in before.</li>
					<li>The barcode must be entered as it appears in the ILS. (including spaces)</li>
					<li>If the user's account is linked to multiple barcodes, only one of the barcodes will work here.</li>
				</ul>
			</div>
			</div>

			<div class="form-group">
				{assign var=property value=$structure.roles}
				{assign var=propName value=$property.property}
				<label for='{$propName}' class="control-label">Roles</label>
				<div class="controls">
					{* Display the list of roles to add *}
					{if isset($property.listStyle) && $property.listStyle == 'checkbox'}
						{foreach from=$property.values item=propertyName key=propertyValue}
							<label class="checkbox">
								<input name='{$propName}[{$propertyValue}]' type="checkbox" value='{$propertyValue}' {if is_array($propValue) && in_array($propertyValue, array_keys($propValue))}checked="checked"{/if} >{$propertyName}
							</label>
						{/foreach}
					{else}
						<select name='{$propName}' id="{$propName}" multiple="multiple">
						{foreach from=$property.values item=propertyName key=propertyValue}
							<option value='{$propertyValue}' {if $propValue == $propertyValue}selected="selected"{/if}>{$propertyName}</option>
						{/foreach}
						</select>
					{/if}
				</div>
			</div>
			<div class="form-group">
				<div class="controls">
					<input type="submit" name="submit" value="Add Administrator" class="btn btn-primary">  <a href='/Admin/{$toolName}?objectAction=list' class="btn btn-default">Return to List</a>
				</div>
			</div>
		</fieldset>
	</form>
</div>
{/strip}