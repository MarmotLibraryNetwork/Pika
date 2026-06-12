{strip}
	{if $production_team}
		{foreach from=$production_team item=member}
			<div class="row archive-field-row">
				<div class="result-label col-sm-4">{$member.role|escape}:</div>
				<div class="result-value col-sm-8">
					{if $member.vocabulary eq 'corporate_body' && $member.tid}
						<a href="/Archive2/Organization/{$member.tid}">{$member.name|escape}</a>
					{elseif $member.tid}
						<a href="/Archive2/Person/{$member.tid}">{$member.name|escape}</a>
					{else}
						{$member.name|escape}
					{/if}
				</div>
			</div>
		{/foreach}
	{/if}
{/strip}