{if $loggedIn}

	{include file="MyAccount/patronWebNotes.tpl"}
	{* Alternate Mobile MyAccount Menu *}
	{include file="MyAccount/mobilePageHeader.tpl"}

	<span class='availableHoldsNoticePlaceHolder'></span>

	<h1 role="heading" aria-level="1" class="h2">{translate text='Fines_page_title'}</h1>
{if $offline}
	<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your {translate text='Fines'|lower} at this time.</div>
{else}

	{if count($userFines) > 0}

		{* Show Fine Alert when the user has no linked accounts *}
		{if  count($userFines) == 1 && $user->fines}
			<div class="alert alert-info">
				Your account has <strong>{$user->fines}</strong> in fines.
			</div>
		{/if}

		{foreach from=$userFines item=fines key=userId name=fineTable}
			{if count($userFines) > 1}<h2 class="h3">{$userAccountLabel.$userId}</h2>{/if}{* Only show account name if there is more than one account. *}
			{if $fines}
			<table id="finesTable{$smarty.foreach.fineTable.index}" class="fines-table table table-striped">
				<thead>
					<tr>
						{if $showDate}
							<th>Date</th>
						{/if}
						<th>Title</th>
						{if $showReason}
							<th>Message</th>
						{/if}
						<th>Amount</th>
						{if $showOutstanding}
							<th>Outstanding</th>
						{/if}
					</tr>
				</thead>
				<tbody>
					{foreach from=$fines item=fine}
						<tr>
							{if $showDate}
								<td>{$fine.date}</td>
							{/if}
							<td>
								{$fine.title|removeTrailingPunctuation}
							</td>
							{if $showReason}
								<td>
									{$fine.reason}
									{if $fine.details}
										{foreach from=$fine.details item=detail}
											<div class="row">
												<div class="col-xs-3"><strong>{$detail.label}</strong></div>
												<div class="col-xs-7">{$detail.value}</div>
											</div>
										{/foreach}
									{/if}
								</td>
							{/if}
							<td>{$fine.amount}</td>
							{if $showOutstanding}
								<td>{$fine.amountOutstanding}</td>
							{/if}
						</tr>

					{/foreach}
				</tbody>
				<tfoot>
				<tr class="info">
					<th>Total</th>
					{if $showDate}
						<td></td>
					{/if}
					{if $showReason}
						<td></td>
					{/if}
					<th>{$fineTotals.$userId}</th>
					{if $showOutstanding}
						<th>{$outstandingTotal.$userId}</th>
					{/if}
				</tr>
				</tfoot>
			</table>
				{else}
				<p class="alert alert-success">This account does not have any fines within the system.</p>
			{/if}
		{/foreach}

		{if $showFinePayments}
			{* We are doing an actual payment of fines online *}
			{include file="MyAccount/finePayments.tpl"}
		{else}
			{* Pay Fines Button *}
			{if $showEcommerceLink && $user->finesVal > $minimumFineAmount}
				<a href="{$ecommerceLink}" target="_blank"{if $showRefreshAccountButton} onclick="Pika.Account.ajaxLightbox('/AJAX/JSON?method=getPayFinesAfterAction')"{/if}>
					<div class="btn btn-primary">{if $payFinesLinkText}{$payFinesLinkText}{else}Click to Pay Fines Online{/if}&nbsp;<small>[Opens in new tab]</small></div>
				</a>
			{/if}
		{/if}

	{else}
		<p class="alert alert-success">You do not have any fines within the system.</p>
	{/if}
{/if}
{else}
    {include file="MyAccount/loginRequired.tpl"}
{/if}
