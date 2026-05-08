{strip}
	{foreach from=$related_person item=person}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">{$person.relation_label|escape}</div>
			<div class="result-value col-sm-8">
				<a href="/Archive2/Person/{$person.tid}">{$person.name|escape}</a>
			</div>
		</div>
	{/foreach}
{/strip}
