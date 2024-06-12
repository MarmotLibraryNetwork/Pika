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
			<div class="alert alert-info">
		{if count($userFines) == 1 && $user->fines}
			<p>Your account has <strong>{$user->fines}</strong> in fines.</p>
		{/if}
				<p>The items below are far enough past their due date that they have been assigned replacement fees. If you return the items, these fees will be removed. If the items are truly lost or damaged, you can visit the library to make arrangements to pay or <a title="Pay Fines Online" href="https://wakenc.comprisesmartpay.com/">pay online</a></p>
			</div>

		{foreach from=$userFines item=fines key=userId name=fineTable}
			{if count($userFines) > 1}<h2 class="h3">{$userAccountLabel.$userId}</h2>{/if}{* Only show account name if there is more than one account. *}
			{if $fines}
			<table id="finesTable{$smarty.foreach.fineTable.index}" class="fines-table table table-striped">
				<thead>
					<tr>
						{if $showDate}
							<th>Date</th>
						{/if}
						{if $showReason}
							<th>Message</th>
						{/if}
						<th>Title</th>
						<th>Fine/Fee Amount</th>
						{if $showOutstanding}
							<th>Amount Outstanding</th>
						{/if}
					</tr>
				</thead>
				<tbody>
					{foreach from=$fines item=fine}
						<tr>
							{if $showDate}
								<td>{$fine.date}</td>
							{/if}
							{if $showReason}
								<td>
									{$fine.reason}
								</td>
							{/if}
							<td>
								{$fine.message|removeTrailingPunctuation}
								{if $fine.details}
									{foreach from=$fine.details item=detail}
										<div class="row">
											<div class="col-xs-5"><strong>{$detail.label}</strong></div>
											<div class="col-xs-7">{$detail.value}</div>
										</div>
									{/foreach}
								{/if}
							</td>
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
