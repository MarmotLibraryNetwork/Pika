{strip}
	{if $production_team}
		{foreach from=$production_team item=member}
			{include file="Archive2/partials/fieldRow.tpl" label=$member.role value=$member.name}
		{/foreach}
	{/if}
{/strip}