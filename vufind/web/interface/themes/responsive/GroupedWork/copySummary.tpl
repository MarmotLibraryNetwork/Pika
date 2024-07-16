{strip}
	{if !empty($summary)}
		{assign var=numDefaultItems value="0"}
		{assign var=numRowsShown value="0"}
		{foreach from=$summary item="item"}
			{if $item.displayByDefault && $numRowsShown<3}
				<div class="itemSummary row">
					<div class="{if $item.isEContent == false}col-xs-6{elseif $inRecordView}col-xs-11{else}col-tn-12{/if}{if $inRecordView} col-xs-offset-1{/if}" title="Location" aria-label="Location">
						{* When on a Record view page, Indent one column, except for narrowest width devices (tn).
						Give larger width (6) to item location since they tend to be longer than the call numbers. (that are given width 5) *}
						<span class="itemSummaryShelfLocation notranslate"><strong>{$item.shelfLocation}</strong>
							{if $item.availableCopies > 1}
							&nbsp;has&nbsp;{$item.availableCopies}
							{/if}
						</span>
					</div>
					{if $item.isEContent == false}
						<div class="{if $inRecordView}col-xs-5{else}col-xs-6{/if}{if !$inEditionsTable} col-tn-offset-1 col-xs-offset-0{/if}" title="Callnumber" aria-label="Callnumber">
							{* The offset column indents the call number on narrow view (tn) when location and call number are in their own row *}
							{* Don't use offset within the editions table *}
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
				<div class="row text-center">
				<button class="itemSummary btn-link" onclick="return Pika.showElementInPopup('Copy Summary', '#itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{if $recordViewUrl}, '#itemSummaryPopupButtons_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{/if});">
					{translate text="Quick Copy View"}
				</button>
				</div>
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