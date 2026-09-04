{strip}
	{foreach from=$notes item=note}
		<div class="row">
			{if !empty($notes)}
				<div class="result-label col-md-4">{$note.label}</div>
				<div class="result-value col-md-8">
					{$note.body}
				</div>
			{else}
				<div class="result-value col-md-12">
					{$note.body}
				</div>
			{/if}
		</div>
	{/foreach}
{/strip}