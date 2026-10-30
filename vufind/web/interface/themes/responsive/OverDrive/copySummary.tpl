{strip}
	{* Copy summary for OverDrive records, used in place of GroupedWork/copySummary.tpl.
	   Counts come from the OverDrive availability, the same as the record view's Copy Details (OverDrive/view-copies.tpl).
	   Parameters: relatedRecords (the OverDrive related records), itemSummaryId *}
	{assign var=odAvailableCopies value=0}
	{assign var=odTotalCopies value=0}
	{assign var=odCollections value=[]}
	{foreach from=$relatedRecords item=odRelatedRecord}
		{if $odRelatedRecord.source == 'overdrive' && !empty($odRelatedRecord.driver)}
			{assign var=odCopySummary value=$odRelatedRecord.driver->getCopySummary()}
			{assign var=odAvailableCopies value=$odAvailableCopies+$odCopySummary.availableCopies}
			{assign var=odTotalCopies value=$odTotalCopies+$odCopySummary.totalCopies}
			{foreach from=$odCopySummary.collections item=odCollection}
				{append var=odCollections value=$odCollection}
			{/foreach}
		{/if}
	{/foreach}
	{if $odAvailableCopies > 0}
		<div class="itemSummary row">
			<div class="col-12" title="Location" aria-label="Location">
				<span class="itemSummaryShelfLocation notranslate"><strong>Online OverDrive Collection</strong>&nbsp;has&nbsp;{$odAvailableCopies}</span>
			</div>
		</div>
	{/if}
	{* Only collections that own copies are listed, so there is nothing to pop-up without them *}
	{if !empty($odCollections)}
		{if !$inPopUp}
			{if count($relatedRecords) == 1}
				{assign var=odRecordViewUrl value=$relatedRecords[0].url}
			{else}
				{assign var=odRecordViewUrl value=''}
			{/if}
			<div class="row text-center">
			<button class="itemSummary btn btn-link" onclick="return Pika.showElementInPopup('Copy Summary', '#itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{if $odRecordViewUrl}, '#itemSummaryPopupButtons_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}'{/if});">
				{translate text="Quick Copy View"}
			</button>
			</div>
			<div id="itemSummaryPopup_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}" class="itemSummaryPopup" style="display: none">
				<table class="table table-striped table-sm itemSummaryTable">
					<thead>
					<tr>
						<th>Available Copies</th>
						<th>Location</th>
						{if count($relatedRecords) > 1}
							<th>Edition</th>
						{/if}
					</tr>
					</thead>
					<tbody>
					{foreach from=$odCollections item=odCollection}
						<tr{if $odCollection.availableCopies > 0} class="available"{/if}>
							<td>{$odCollection.availableCopies} of {$odCollection.totalCopies}</td>
							<td class="notranslate">{$odCollection.name}</td>
							{if count($relatedRecords) > 1}
								<td class="notranslate">{$odCollection.edition}</td>
							{/if}
						</tr>
					{/foreach}
					</tbody>
				</table>
			</div>
			{if $odRecordViewUrl}
				<div id="itemSummaryPopupButtons_{$itemSummaryId|escapeCSS}_{$relatedManifestation.format|escapeCSS}" style="display: none">
					<a href="{$odRecordViewUrl}" class="btn btn-primary" role="button">{translate text="See Full Copy Details"}</a>
				</div>
			{/if}
		{/if}
	{/if}
{/strip}
