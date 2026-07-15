<li class="breadcrumb-item"><a href="/MyAccount/Home">{translate text='Your Account'}</a></li>
{if $reportData}
	<li class="breadcrumb-item"><a href="{$reportData.parentLink}{if $filterString}?{$filterString}{/if}">{$reportData.parentName}</a></li>
	<li class="breadcrumb-item"><em>{$reportData.name}</em></li>
{elseif $action != 'Dashboard'}
	<li class="breadcrumb-item"><a href="/Report/Dashboard">{translate text='Dashboard'}</a></li>
	<li class="breadcrumb-item">
		{if $pageTitle}
			<em>{$pageTitle}</em>
		{elseif $shortTitle}
			<em>{$shortTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}

	</li>
{/if}

