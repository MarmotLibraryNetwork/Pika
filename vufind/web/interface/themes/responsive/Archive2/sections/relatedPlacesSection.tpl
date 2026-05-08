{strip}
	{foreach from=$related_place item=place}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">{$place.relation_label|escape}</div>
			<div class="result-value col-sm-8">
				<a href="/Archive2/Place/{$place.tid}">{$place.name|escape}</a>
			</div>
		</div>
	{/foreach}
{/strip}
