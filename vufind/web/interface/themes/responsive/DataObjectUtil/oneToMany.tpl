{strip}
<div class="controls table-responsive">
	<table id="{$propName}" class="{if $property.sortable}sortableProperty{/if} table table-striped">
		<thead>
			<tr>
				{if $property.sortable}
					<th class="sorter-false filter-false">Sort</th>
				{/if}
				{foreach from=$property.structure item=subProperty}
					{if in_array($subProperty.type, array('text', 'enum', 'date', 'checkbox', 'integer', 'number', 'textarea', 'html', 'multiSelect')) }
						<th{if in_array($subProperty.type, array('text', 'enum', 'html', 'multiSelect'))} style="min-width:150px"{/if} class="{if $subProperty.type == 'text'}sorter-text-input{elseif $subProperty.type == 'enum'}sorter-text-select{else}sorter-false filter-false{/if}"{if $subProperty.description}  title="{$subProperty.description}"{/if}>{$subProperty.label}</th>
					{/if}
				{/foreach}
				<th class="sorter-false filter-false">Actions</th>
			</tr>
		</thead>
		<tbody>
		{foreach from=$propValue item=subObject}
			<tr id="{$propName}{$subObject->id}">
				<input type="hidden" id="{$propName}Id_{$subObject->id}" name="{$propName}Id[{$subObject->id}]" value="{$subObject->id}">
				{if $property.sortable}
					<td>
					<span class="glyphicon glyphicon-resize-vertical"></span>
					<input type="hidden" id="{$propName}Weight_{$subObject->id}" name="{$propName}Weight[{$subObject->id}]" value="{$subObject->weight}">
					</td>
				{/if}
				{foreach from=$property.structure item=subProperty}
					{if in_array($subProperty.type, array('text', 'enum', 'date', 'checkbox', 'integer', 'number', 'textarea', 'html')) }
						<td>
							{assign var=subPropName value=$subProperty.property}
							{assign var=subPropValue value=$subObject->$subPropName}
{*							{if $subProperty.type=='text' || $subProperty.type=='date' || $subProperty.type=='integer' || $subProperty.type=='textarea' || $subProperty.type=='html'}*}
							{if in_array($subProperty.type, array('text', 'date', 'integer', 'textarea', 'html'))}
								<input type="text" name="{$propName}_{$subPropName}[{$subObject->id}]" value="{$subPropValue|escape}" aria-label="{$subProperty.label}" class="form-control{if $subProperty.type=='date'} datepicker{elseif $subProperty.type=="integer"} integer{/if}{if $subProperty.required == true} required{/if}">
							{elseif $subProperty.type=='number'}
								<input type="number" name='{$propName}_{$subPropName}[{$subObject->id}]' value="{$subPropValue|escape}" class="form-control {if $subProperty.required}required{/if}"{if $subProperty.max} max="{$subProperty.max}"{/if}{if $subProperty.min} min="{$subProperty.min}"{/if}{if $subProperty.maxLength} maxlength='{$subProperty.maxLength}'{/if}{if $subProperty.size} size='{$subProperty.size}'{/if}{if $subProperty.step} step='{$subProperty.step}'{/if}>
							{elseif $subProperty.type=='checkbox'}
								<input type="checkbox" name='{$propName}_{$subPropName}[{$subObject->id}]' aria-label="{$subProperty.label}" {if $subPropValue == 1}checked='checked'{/if}>
							{else}
								<select name='{$propName}_{$subPropName}[{$subObject->id}]' id='{$propName}{$subPropName}_{$subObject->id}' aria-label="{$subProperty.label}" class='form-control {if $subProperty.required == true} required{/if}'>
								{foreach from=$subProperty.values item=propertyName key=propertyValue}
									<option value='{$propertyValue}' {if $subPropValue == $propertyValue}selected='selected'{/if}>{$propertyName}</option>
								{/foreach}
								</select>
							{/if}
						</td>
					{elseif $subProperty.type == 'multiSelect'}
						{if $subProperty.listStyle == 'checkboxList'}
							<td>
								<div class="checkbox">
									{*this assumes a simple array, eg list *}
									{assign var=subPropName value=$subProperty.property}
									{assign var=subPropValue value=$subObject->$subPropName}
									{foreach from=$subProperty.values item=propertyName}
										<input name='{$propName}_{$subPropName}[{$subObject->id}][]' type="checkbox" value='{$propertyName}' {if is_array($subPropValue) && in_array($propertyName, $subPropValue)}checked='checked'{/if}> {$propertyName}<br>
									{/foreach}
								</div>
							</td>
						{/if}
					{/if}
				{/foreach}
				<td>
				{* link to delete*}
				<input type="hidden" id="{$propName}Deleted_{$subObject->id}" name="{$propName}Deleted[{$subObject->id}]" value="false">
					{* link to delete *}
				<a href="#" aria-label="Delete entry" onclick="if (confirm('Are you sure you want to delete this?')){literal}{{/literal}$('#{$propName}Deleted_{$subObject->id}').val('true');$('#{$propName}{$subObject->id}').hide().find('.required').removeClass('required'){literal}}{/literal};return false;">
					{* On delete action, also remove class 'required' to turn off form validation of the deleted input; so that the form can be submitted by the user  *}
					<span class="glyphicon glyphicon-remove-circle" title="Delete" aria-hidden="true" style="color: red;"></span>
				</a>
				{if $property.editLink neq ''}
					&nbsp;<a href='{$property.editLink}?objectAction=edit&widgetListId={$subObject->id}&widgetId={$widgetid}' aria-label='Edit SubLinks' title='Edit SubLinks'>
						<span class="glyphicon glyphicon-link" title="edit links">&nbsp;</span>
					</a>
				{elseif $property.canEdit}
					{if method_exists($subObject, 'getEditLink')}
						{assign var="editLink" value=$subObject->getEditLink()}
							{if $editLink}
								&nbsp;<a href='{$editLink}' title='Edit'>
									<span class="glyphicon glyphicon-edit" title="edit">&nbsp;</span>
								</a>
						{/if}
					{else}
						Please add a getEditLink method to this object
					{/if}
				{/if}
				{if $property.directLink}
					{if method_exists($subObject, 'getDirectLink')}
						{assign var="directLink" value=$subObject->getDirectLink()}
						{if $directLink}
						&nbsp;<a href='{$subObject->getDirectLink()}' title='Direct Link'>
							<span class="glyphicon glyphicon-link" >&nbsp;</span>
						</a>
						{/if}
					{else}
						Please add a getDirectLink method to this object
					{/if}
				{/if}
				</td>
			</tr>
		{foreachelse}
			<tr style="display:none"><td></td></tr>
		{/foreach}
		</tbody>
	</table>

	<div class="{$propName}Actions">
		<a href="#" onclick="addNew{$propName}();return false;"  class="btn btn-primary btn-sm">Add New</a>
		{if $property.additionalOneToManyActions && $id}{* Only display these actions for an existing object *}
			<div class="btn-group pull-right">
				{foreach from=$property.additionalOneToManyActions item=action}
					{assign var="actionAllowed" value=false}
					{if is_array($action.allowed_roles)}
						{if !empty($userRoles)}
							{foreach from=$action.allowed_roles item=allowedRole}
								{if in_array($allowedRole, $userRoles)}
									{assign var="actionAllowed" value=true}
								{/if}
							{/foreach}
						{/if}
					{elseif	$action.allowed_roles && !empty($userRoles) && in_array($action.allowed_roles, $userRoles)}
						{assign var="actionAllowed" value=true}
					{/if}
					{if $actionAllowed}
					<a class="btn {if $action.class}{$action.class}{else}btn-default{/if} btn-sm"{if $action.url} href="{$action.url|replace:'$id':$id}"{/if}{if $action.onclick} onclick="{$action.onclick|replace:'$id':$id}"{/if}>{$action.text}</a>
					{elseif $action.allowed_roles && $userRoles && (!in_array($action.allowed_roles, $userRoles))}
						<small></small>
					{else}
					<a class="btn {if $action.class}{$action.class}{else}btn-default{/if} btn-sm"{if $action.url} href="{$action.url|replace:'$id':$id}"{/if}{if $action.onclick} onclick="{$action.onclick|replace:'$id':$id}"{/if}>{$action.text}</a>
					{/if}

				{/foreach}
			</div>
		{/if}
	</div>
	{/strip}
	<script>
		{literal}$(function(){{/literal}
		{if $property.sortable}
			{literal}$('#{/literal}{$propName}{literal} tbody').sortable({
				update: function(event, ui){
					$.each($(this).sortable('toArray'), function(index, value){
						var inputId = '#{/literal}{$propName}Weight_' + value.substr({$propName|@strlen}); {literal}
						$(inputId).val(index +1);
					});
				}
			});
			{/literal}
		{/if}
		{literal}$('.datepicker').datepicker({format:"yyyy-mm-dd"});{/literal}
		{literal}});{/literal}
		var numAdditional{$propName} = 0;
		function addNew{$propName}{literal}(){
			numAdditional{/literal}{$propName}{literal} = numAdditional{/literal}{$propName}{literal} -1;
			var newRow = "<tr>";
			{/literal}
			newRow += "<input type='hidden' id='{$propName}Id_" + numAdditional{$propName} + "' name='{$propName}Id[" + numAdditional{$propName} + "]' value='" + numAdditional{$propName} + "'>";
			{if $property.sortable}
				newRow += "<td><span class='glyphicon glyphicon-resize-vertical'></span>";
				newRow += "<input type='hidden' id='{$propName}Weight_" + numAdditional{$propName} +"' name='{$propName}Weight[" + numAdditional{$propName} +"]' value='" + (100 - numAdditional{$propName})  +"'>";
				newRow += "</td>";
			{/if}
			{foreach from=$property.structure item=subProperty}
				{if in_array($subProperty.type, array('text', 'enum', 'date', 'checkbox', 'integer', 'number', 'textarea', 'html')) }
					newRow += "<td>";
					{assign var=subPropName value=$subProperty.property}
					{assign var=subPropValue value=$subObject->$subPropName}
					{if $subProperty.type=='text' || $subProperty.type=='date' || $subProperty.type=='integer' || $subProperty.type=='textarea' || $subProperty.type=='html'}
						newRow += "<input type='text' name='{$propName}_{$subPropName}[" + numAdditional{$propName} +"]' value='{if $subProperty.default}{$subProperty.default}{/if}' class='form-control{if $subProperty.type=="date"} datepicker{elseif $subProperty.type=="integer"} integer{/if}{if $subProperty.required == true} required{/if}'>";
					{elseif $subProperty.type=='number'}
						newRow += "<input type='number' name='{$propName}_{$subPropName}[" + numAdditional{$propName} +"]' value='{if $subProperty.default}{$subProperty.default}{/if}' class='form-control{if $subProperty.required == true} required{/if}'{if $subProperty.max} max='{$subProperty.max}'{/if}{if $subProperty.min} min='{$subProperty.min}'{/if}{if $subProperty.maxLength} maxlength='{$subProperty.maxLength}'{/if}{if $subProperty.size} size='{$subProperty.size}'{/if}{if $subProperty.step} step='{$subProperty.step}'{/if}>";
					{elseif $subProperty.type=='checkbox'}
						newRow += "<input type='checkbox' name='{$propName}_{$subPropName}[" + numAdditional{$propName} +"]' {if $subProperty.default == 1}checked='checked'{/if}>";
					{else}
						newRow += "<select name='{$propName}_{$subPropName}[" + numAdditional{$propName} +"]' id='{$propName}{$subPropName}_" + numAdditional{$propName} +"' class='form-control{if $subProperty.required == true} required{/if}'>";
						{foreach from=$subProperty.values item=propertyName key=propertyValue}
							newRow += "<option value='{$propertyValue}' {if $subProperty.default == $propertyValue}selected='selected'{/if}>{$propertyName}</option>";
						{/foreach}
						newRow += "</select>";
					{/if}
					newRow += "</td>";
				{elseif $subProperty.type == 'multiSelect'}
					{if $subProperty.listStyle == 'checkboxList'}
					newRow += '<td>';
					newRow += '<div class="checkbox">';
					{*this assumes a simple array, eg list *}
					{assign var=subPropName value=$subProperty.property}
					{assign var=subPropValue value=$subObject->$subPropName}
					{foreach from=$subProperty.values item=propertyName}
					newRow += '<input name="{$propName}_{$subPropName}[' + numAdditional{$propName} + '][]" type="checkbox" value="{$propertyName}"> {$propertyName}<br>';
					{/foreach}
					newRow += '</div>';
					newRow += '</td>';
					{/if}
				{/if}
			{/foreach}
			newRow += "</tr>";
			{literal}
			$('#{/literal}{$propName}{literal} tr:last').after(newRow);
			$('.datepicker').datepicker({format:"yyyy-mm-dd"});
			return false;
		}
		{/literal}
	</script>
	{if $propName == "translationMapValues"}

	<script>

		{literal}
		$.fn.dataTable.ext.order['dom-text'] = function  ( settings, col )
		{
			return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
				return $('input', td).val();
			} );
		}

		$(document).ready( function(){
			$('#translationMapValues thead th:not(:last-child)').each( function () {
				var title = $(this).text();
				$(this).html('<input type="text" placeholder="Search ' + title + '">');
			});
				$("#translationMapValues").DataTable({
					"columns": [
						{"orderDataType": "dom-text", type: 'string'},
						{"orderDataType": "dom-text", type: 'string'},
						null
					],
					paging: false,
					"dom": 'lrtip',
					initComplete: function () {
						this.api().columns([0, 1]).every(function () {
							var that = this;

							$('input', this.header())
											.on('keyup change clear', function () {
												if (that.search() !== this.value) {
													that
																	.search(this.value)
																	.draw();
												}
											})
											.on('click', function (e) {
												e.stopPropagation();
											});
						});
					}
				});
			});
		{/literal}
	</script>

	{/if}
</div>