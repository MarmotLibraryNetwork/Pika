{strip}
	{if !empty($summary)}
		{assign var=numDefaultItems value="0"}
		{assign var=numRowsShown value="0"}
		{foreach from=$summary item="item"}
			{if $item.displayByDefault && $numRowsShown<3}
				<div class="itemSummary row">
					<div class="{if $item.isEContent == false}col-xs-7{else}col-xs-12{/if}">
						<span class="itemSummaryShelfLocation notranslate"><strong>{$item.shelfLocation}</strong>
							{if $item.availableCopies > 1}
							&nbsp;has&nbsp;{$item.availableCopies}
							{/if}
						</span>
					</div>
					{if $item.isEContent == false}
						<div class="col-xs-4">
							<span class="itemSummaryCallNumber notranslate"><strong>{$item.callNumber}</strong></span>
						</div>
					{/if}
				</div>
				{assign var=numDefaultItems value=$numDefaultItems+$item.totalCopies}
				{assign var=numRowsShown value=$numRowsShown+1}
			{/if}
		{/foreach}
		{if !$inPopUp}
			{assign var=numRemainingCopies value=$totalCopies-$numDefaultItems}
			{if $numRemainingCopies > 0}
				<button class="itemSummary btn-link" onclick="return Pika.showElementInPopup('Copy Summary', '#itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{if $recordViewUrl}, '#itemSummaryPopupButtons_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{/if});">
					{translate text="Quick Copy View"}
				</button>
				<div id="itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}" class="itemSummaryPopup" style="display: none">
					<table class="table table-striped table-condensed itemSummaryTable">
						<thead>
						<tr>
							<th>Available Copies</th>
							<th>Location</th>
							<th>Call Number</th>
						</tr>
						</thead>
						<tbody>
						{assign var=numRowsShown value=0}
						{foreach from=$summary item="item"}
							<tr{if $item.availableCopies} class="available"{/if}>
								{if $item.onOrderCopies > 0}
									{if $showOnOrderCounts}
										<td>{$item.onOrderCopies} on order</td>
									{else}
										<td>Copies on order</td>
									{/if}
								{else}
									<td>{$item.availableCopies} of {$item.totalCopies}</td>
								{/if}
								<td class="notranslate">{$item.shelfLocation}</td>
								<td class="notranslate">
									{if !$item.isEContent}
										{$item.callNumber}
									{/if}
								</td>
							</tr>
						{/foreach}
						</tbody>
					</table>
				</div>
				{if $recordViewUrl}
					<div id="itemSummaryPopupButtons_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}" {*class="itemSummaryPopup"*} style="display: none">
						<a href="{$recordViewUrl}" class="btn btn-primary" role="button">{translate text="See Full Copy Details"}</a>
					</div>
				{/if}
			{/if}
		{/if}
	{/if}
{/strip}