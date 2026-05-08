{strip}
	{foreach from=$related_event item=event}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">{$event.relation_label|escape}</div>
			<div class="result-value col-sm-8">
				<a href="/Archive2/Event/{$event.tid}">{$event.name|escape}</a>
			</div>
		</div>
	{/foreach}
{/strip}
