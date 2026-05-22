{strip}
	{include file="Archive2/partials/fieldRow.tpl" label="Material" value=$material}
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
	{include file="Archive2/partials/fieldRow.tpl" label="Style / Period" value=$style_period}
	{include file="Archive2/partials/fieldRow.tpl" label="Measurement" value=$measurement}
{/strip }
