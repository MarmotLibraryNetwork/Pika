{strip}
	<li>
		<a href="/MyAccount/Home">{translate text='Your Account'}</a> <span class="divider">&raquo;</span>
	</li>
	{if $pageTemplate|strstr:"list.tpl"}
		<li>
			<a href="/MyAccount/MyLists">{translate text='My Lists'}</a> <span class="divider">&raquo;</span>
		</li>
	{/if}
	<li>
		{if $shortPageTitle}
			<em>{$shortPageTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}
		<span class="divider">&raquo;</span>
	</li>
{/strip}