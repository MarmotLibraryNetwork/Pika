{strip}
	{if $linked_agents_display || $debugDetails}
		{if $linked_agents_display}
			{foreach from=$linked_agents_display item=agent}
				<div class="row archive-field-row">
					<div class="result-label col-sm-4">{$agent.label|escape}: </div>
					<div class="result-value col-sm-8">
						{if $agent.vocabulary eq 'corporate_body' && $agent.tid}
							<a href="/Archive2/Organization/{$agent.tid}">{$agent.name|escape}</a>
						{elseif $agent.vocabulary eq 'person' && $agent.tid}
							<a href="/Archive2/Person/{$agent.tid}">{$agent.name|escape}</a>
						{else}
							{$agent.name|escape}
						{/if}
					</div>
				</div>
			{/foreach}
		{else}
			<div class="row archive-field-row">
				<div class="result-label col-sm-4">Linked Agent: </div>
				<div class="result-value col-sm-8"><span class="text-muted">Not provided</span></div>
			</div>
		{/if}
	{/if}

	{include file="Archive2/partials/fieldRow.tpl" label="Date Created" value=$edtf_date_created}
	{include file="Archive2/partials/fieldRow.tpl" label="Date Issued" value=$edtf_date_issued}
	{include file="Archive2/partials/fieldRow.tpl" label="Date" value=$edtf_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Date Captured" value=$date_captured}
	{include file="Archive2/partials/fieldRow.tpl" label="Copyright Date" value=$copyright_date}
	{include file="Archive2/partials/fieldRow.tpl" label="Date (Text)" value=$date_text}
	{include file="Archive2/partials/fieldRow.tpl" label="Postmark" value=$postmark}
	{include file="Archive2/partials/fieldRow.tpl" label="Physical Form" value=$physical_form}
	{include file="Archive2/partials/fieldRow.tpl" label="Extent" value=$extent}
	{include file="Archive2/partials/fieldRow.tpl" label="Statement of Responsibility" value=$statement_of_responsibility}
	{include file="Archive2/partials/fieldRow.tpl" label="Publisher" value=$publisher}
{/strip}
