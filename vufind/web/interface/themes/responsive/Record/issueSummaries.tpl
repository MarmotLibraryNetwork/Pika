{strip}
	{* Display Issue Summaries *}
	{foreach from=$issueSummaries item=issueSummary name=summaryLoop}
		<div class="issue-summary-row">
			{if $issueSummary.location}
				<div class="issue-summary-location">{$issueSummary.location}</div>
			{/if}
			<div class="issue-summary-details">
				{if $issueSummary.identity}
					<div class="row">
						<div class="result-label col-xs-4">Identity</div>
						<div class="result-value col-xs-8">{$issueSummary.identity}</div>
					</div>
				{/if}
				{if $issueSummary.callNumber}
					<div class="row">
						<div class="result-label col-xs-4">Call Number</div>
						<div class="result-value col-xs-12">{$issueSummary.callNumber}</div>
					</div>
				{/if}
				{if $issueSummary.latestReceived}
					<div class="row">
						<div class="result-label col-xs-4">Latest Issue Received</div>
						<div class="result-value col-xs-8">{$issueSummary.latestReceived}</div>
					</div>
				{/if}
				{if isset($issueSummary.holdingStatement) }
					<div class="row">
						<div class="result-label col-xs-4">Holdings</div>
						<div class="result-value col-xs-8">{$issueSummary.holdingStatement}</div>
					</div>
				{/if}
				{if $issueSummary.libHas}
						<div class="row">
							<div class="result-label col-xs-4">Library Has</div>
							<div class="result-value col-xs-8">{$issueSummary.libHas}</div>
						</div>
				{/if}

				{if count($issueSummary.holdings) > 0}
					<button onclick="VuFind.showMessage('{$issueSummary.location}', $('#issue-summary-holdings-{$smarty.foreach.summaryLoop.iteration}').html())" class="btn btn-xs btn-info">Show Individual Issues</button>
					&nbsp;
				{/if}

				{if $showCheckInGrid && $issueSummary.checkInGridId}
					<button onclick="VuFind.Account.ajaxLightbox('{$path}/{$activeRecordProfileModule}/{$id}/AJAX?method=getCheckInGrid&checkInGridId={$issueSummary.checkInGridId}');" class="btn btn-xs btn-info">Show Check-in Grid</button>
				{/if}
			</div>

			{if count($issueSummary.holdings) > 0}
				<div id='issue-summary-holdings-{$smarty.foreach.summaryLoop.iteration}' class="issue-summary-holdings striped" style="display:none;">
					{include file="Record/copiesTableHeader.tpl"}
					{foreach from=$issueSummary.holdings item=holding}
						{include file="Record/copiesTableRow.tpl"}
					{/foreach}
				</div>
			{/if}
		</div>
	{/foreach}
{/strip}