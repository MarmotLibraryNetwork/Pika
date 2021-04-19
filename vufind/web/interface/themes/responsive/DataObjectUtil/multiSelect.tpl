<div class="controls">
	{if isset($property.listStyle)}
		{if $property.listStyle == 'checkbox'}
			<div class="checkbox">
				{* Original Behavior *}
				{foreach from=$property.values item=propertyName key=propertyValue}
					<input name='{$propName}[{$propertyValue}]' type="checkbox" value='{$propertyValue}' {if is_array($propValue) && in_array($propertyValue, array_keys($propValue))}checked='checked'{/if}> {$propertyName}<br>
				{/foreach}
			</div>
		{elseif $property.listStyle == 'checkboxSimple'}
			{* Below used to follow template behaviour

			{assign var=temp2 value=$propValue|@array_keys}
			{assign var=temp value=$propValue|@array_values}
					keys :{','|implode:$temp2}<br>
					values:{','|implode:$temp}<br>
			*}

			<div class="checkbox">
				{* Modified Behavior: $propertyValue is used only as a display name to the user *}

				{foreach from=$property.values item=propertyName key=propertyValue}
					<input name='{$propName}[]' type="checkbox" value='{$propertyValue}' {if is_array($propValue) && in_array($propertyValue, $propValue)}checked='checked'{/if}> {$propertyName}<br>
				{/foreach}
			</div>
		{elseif $property.listStyle == 'checkboxList'}
			<div class="checkbox">
				{*this assumes a simple array, eg list *}
				{foreach from=$property.values item=propertyName}
					<input name='{$propName}[]' type="checkbox" value='{$propertyName}' {if is_array($propValue) && in_array($propertyName, $propValue)}checked='checked'{/if}> {$propertyName}<br>
				{/foreach}
			</div>
		{/if}
	{else}
		<br>
		<select name='{$propName}' id='{$propName}' multiple="multiple">
		{foreach from=$property.values item=propertyName key=propertyValue}
			<option value='{$propertyValue}' {if $propValue == $propertyValue}selected='selected'{/if}>{$propertyName}</option>
		{/foreach}
		</select>
	{/if}
</div>