{strip}
	{if $description}
		<div class="row">
			<div class="result-value col-sm-12">{$description}</div>
		</div>
		<hr>
	{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Language" value=$languageName}
	{include file="Archive2/partials/fieldRow.tpl" label="Resource Type" value=$resource_type.name}
	{include file="Archive2/partials/fieldRow.tpl" label="Genre" value=$genre.name}
	{include file="Archive2/partials/fieldRow.tpl" label="Physical Description" value=$physical_description}
{/strip}
