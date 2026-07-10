{strip}
{if $research_type || $research_level || $published_in || $conference_date || $presented_at || $supporting_departments || $debugDetails}
	{include file="Archive2/partials/fieldRow.tpl" label="Research Type" value=$research_type|ucfirst}
	{include file="Archive2/partials/fieldRow.tpl" label="Research Level" value=$research_level|ucfirst}
	{include file="Archive2/partials/fieldRow.tpl" label="Published In" value=$published_in}
	{if $presented_at || $debugDetails}
		<div class="row archive-field-row">
			<div class="result-label col-md-4">Presented At:</div>
			<div class="result-value col-md-8">
				{if $presented_at}
					{if $presented_at_event_tid}
						<a href="/Archive2/Event/{$presented_at_event_tid}">{$presented_at|escape}</a>
					{else}
						{$presented_at|escape}
					{/if}
				{else}
					<span class="text-muted">Not provided</span>
				{/if}
			</div>
		</div>
	{/if}
	{include file="Archive2/partials/fieldRow.tpl" label="Conference Date" value=$conference_date}
	{if $supporting_departments || $debugDetails}
		<div class="row archive-field-row">
			<div class="result-label col-md-4">Supporting Departments:</div>
			<div class="result-value col-md-8">
				{if $supporting_departments}
					{foreach from=$supporting_departments item=dept}
						<div><a href="/Archive2/Organization/{$dept.tid}">{$dept.name|escape}</a></div>
					{/foreach}
				{else}
					<span class="text-muted">Not provided</span>
				{/if}
			</div>
		</div>
	{/if}
{/if}
{/strip}