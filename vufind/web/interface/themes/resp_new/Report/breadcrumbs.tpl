<li><a href="/MyAccount/Home">{translate text='Your Account'}</a> <span class="divider">&raquo;</span></li>
{if $reportData}
	<li><a href="{$reportData.parentLink}{if $filterString}?{$filterString}{/if}">{$reportData.parentName}</a> <span class="divider">&raquo;</span></li>
	<li><em>{$reportData.name}</em></li>
{elseif $action != 'Dashboard'}
	<li><a href="/Report/Dashboard">{translate text='Dashboard'}</a> <span class="divider">&raquo;</span></li>
	<li>
		{if $pageTitle}
			<em>{$pageTitle}</em>
		{elseif $shortTitle}
			<em>{$shortTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}
		<span class="divider">&raquo;</span>
	</li>
{/if}

