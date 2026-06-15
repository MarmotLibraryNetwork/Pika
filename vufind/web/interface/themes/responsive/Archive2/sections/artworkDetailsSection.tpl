{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Material Description" value=$material_description}
	{if $material}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Material:</div>
			<div class="result-value col-sm-8">
				{if is_array($material)}
					{foreach from=$material item=materialLine}
						<div>{$materialLine|escape}</div>
					{/foreach}
				{else}
					{$material|escape}
				{/if}
			</div>
		</div>
	{/if}
	{if $artMaterials}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Art Materials:</div>
			<div class="result-value col-sm-8">
				{foreach from=$artMaterials item=artMat}
					<div>
						{if $artMat.aatNumber}
							<a href="/Archive2/Results?lookfor={$artMat.aatNumber|escape:url}">{$artMat.name|escape}</a>
						{else}
							{$artMat.name|escape}
						{/if}
					</div>
				{/foreach}
			</div>
		</div>
	{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Technique" value=$technique}
	{if $artTechniques}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Art Technique:</div>
			<div class="result-value col-sm-8">
				{foreach from=$artTechniques item=artTech}
					<div>
						{if $artTech.aatNumber}
							<a href="/Archive2/Results?lookfor={$artTech.aatNumber|escape:url}">{$artTech.name|escape}</a>
						{else}
							{$artTech.name|escape}
						{/if}
					</div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $stylePeriods}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Style / Period:</div>
			<div class="result-value col-sm-8">
				{foreach from=$stylePeriods item=stylePeriod}
					<div>
						{if $stylePeriod.aatNumber}
							<a href="/Archive2/Results?lookfor={$stylePeriod.aatNumber|escape:url}">{$stylePeriod.name|escape}</a>
						{else}
							{$stylePeriod.name|escape}
						{/if}
					</div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $measurement}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Measurement:</div>
			<div class="result-value col-sm-8">
				{if is_array($measurement)}
					{foreach from=$measurement item=measurementLine}
						<div>{$measurementLine|escape}</div>
					{/foreach}
				{else}
					{$measurement|escape}
				{/if}
			</div>
		</div>
	{/if}
	{if $installationDates}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">{$installationLabel}:</div>
			<div class="result-value col-sm-8">
				{foreach from=$installationDates item=installationDate}
					<div>{$installationDate|escape}</div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $coordinates.lat || $coordinates.lng}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Installation Location:</div>
			<div class="result-value col-sm-8">
				<dl class="archive-field-values list-unstyled">
					{if $coordinates.lat}
						<dt>Latitude:</dt>
						<dd>{$coordinates.lat|escape}</dd>
					{/if}
					{if $coordinates.lng}
						<dt>Longitude:</dt>
						<dd>{$coordinates.lng|escape}</dd>
					{/if}
					{if $coordinates.lat && $coordinates.lng}
						<dt>Coordinates:</dt>
						<dd>({$coordinates.lat|escape}, {$coordinates.lng|escape})</dd>
					{/if}
				</dl>
			</div>
		</div>
	{/if}
{/strip}
