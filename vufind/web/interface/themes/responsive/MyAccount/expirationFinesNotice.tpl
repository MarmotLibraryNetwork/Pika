{strip}
	{if !$offline}
		{* No need to calculate total fines if in offline mode*}
		{assign var="totalFines" value=$user->getTotalFines()}
		{if ($showFines && $totalFines > 0) || ($showExpirationWarnings && $user->expireClose)}
			<div id="myAccountFines">
				{if $showFines && ($totalFines > $minimumFineAmount)}
					{if $showEcommerceLink}
						<div class="myAccountLink">
							<a href="{$ecommerceLink}" target="_blank"{if $showRefreshAccountButton} onclick="VuFind.Account.ajaxLightbox('/AJAX/JSON?method=getPayFinesAfterAction')"{/if}  style="color:red; font-weight:bold;">
								Your account{if count($user->getLinkedUsers())>0}s have{else} has{/if} ${$totalFines|number_format:2} in {translate text='Fines'}.
							</a>
						</div>
						<div class="myAccountLink">
							<a href="{$ecommerceLink}" target="_blank"{if $showRefreshAccountButton} onclick="VuFind.Account.ajaxLightbox('/AJAX/JSON?method=getPayFinesAfterAction')"{/if}>
								{if $payFinesLinkText}{$payFinesLinkText}{else}Pay {translate text='Fines'} Online{/if}
							</a>
						</div>
					{else}
						<div class="myAccountLink" title="Please contact your local library to pay {translate text='Fines'} or charges." style="color:red; font-weight:bold;" onclick="alert('Please contact your local library to pay {translate text='Fines'} or charges.')">
							Your account{if count($user->getLinkedUsers())>0}s have{else} has{/if} ${$totalFines|number_format:2} in {translate text='Fines'}.
						</div>
					{/if}
				{/if}

				{if $showExpirationWarnings && $user->expireClose}
					<div class="myAccountLink">
						<a class="alignright" title="Please contact your local library to have your library card renewed." style="color:red; font-weight:bold;" onclick="alert('Please Contact your local library to have your library card renewed.')" href="#">
							{if $user->expired}
								{if $expiredMessage}
									{$expiredMessage}
								{else}
									Your library card expired on {$user->expires}.
								{/if}
							{else}
								{if $expirationNearMessage}
									{$expirationNearMessage}
								{else}
									Your library card will expire on {$user->expires}.
								{/if}
							{/if}
						</a>
					</div>
				{/if}

			</div>
			<hr class="menu">
		{/if}
	{/if}
{/strip}
