{strip}
	<div id="checkInGrid">
		{foreach from=$checkInGrid item=checkInCell}
			<div class="checkInCell col-tn-12 col-xs-6 col-sm-4 col-md-3">
				<div class="issueInfo">
					{$checkInCell.issueDate}{if $checkInCell.issueNumber} ({$checkInCell.issueNumber}){/if}
				</div>
				<div class="status">
					{if $checkInCell.status}
					<span class="{$checkInCell.class}">{$checkInCell.status}</span>
					{/if}
					{if $checkInCell.statusDate} on {$checkInCell.statusDate}{/if}
				</div>
				{if $checkInCell.copies}
					<div class="copies">
						{$checkInCell.copies} {if $checkInCell.copies > 1}Copies{else}Copy{/if}
					</div>
				{/if}
			</div>
		{/foreach}
	</div>
	<div class="clearfix"></div>
{/strip}