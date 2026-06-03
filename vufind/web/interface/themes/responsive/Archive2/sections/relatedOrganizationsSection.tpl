{strip}
	{foreach from=$related_organization item=org}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">{$org.relation_label|escape}</div>
			<div class="result-value col-sm-8">
				<a href="/Archive2/Organization/{$org.tid}">{$org.name|escape}</a>
			</div>
		</div>
	{/foreach}
{/strip}
