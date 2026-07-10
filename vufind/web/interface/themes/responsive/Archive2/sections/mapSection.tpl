{strip}
	{if $coordinates && is_array($coordinates)}
		{if $coordinates_text}
			<div class="row archive-field-row">
				<div class="result-label col-md-4">Location:</div>
				<div class="result-value col-md-8">{$coordinates_text|escape}</div>
			</div>
		{/if}
		{include file="Archive2/partials/fieldRow.tpl" label="Latitude"  value=$coordinates.lat}
		{include file="Archive2/partials/fieldRow.tpl" label="Longitude" value=$coordinates.lng}
		<div class="row">
			<div class="col-md-12">
				<iframe title="Google map for {$title|escape}" width="100%" height="300px" class="taxonomy-map-embed"
					src="https://www.google.com/maps/embed/v1/place?q={$coordinates.lat|escape}%2C%20{$coordinates.lng|escape}&key={$maps_key|escape}" allowfullscreen></iframe>
			</div>
		</div>
	{elseif $coordinates}
		{include file="Archive2/partials/fieldRow.tpl" label="Coordinates" value=$coordinates}
	{/if}
{/strip}