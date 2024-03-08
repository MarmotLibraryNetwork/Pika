{strip}
	<li>
		<a href="/MyAccount/Home">{translate text='Your Account'}</a> <span class="divider">&raquo;</span>
	</li>
	<li>
		{if $pageTitle}
			<em>{$pageTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}
		<span class="divider">&raquo;</span>
	</li>
{/strip}