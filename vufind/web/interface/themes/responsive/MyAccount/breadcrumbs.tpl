{strip}
	<li class="breadcrumb-item">
		<a href="/MyAccount/Home">{translate text='Your Account'}</a>
	</li>
	{if $pageTemplate|strstr:"list.tpl"}
		<li class="breadcrumb-item">
			<a href="/MyAccount/MyLists">{translate text='My Lists'}</a>
		</li>
	{/if}
	<li class="breadcrumb-item active" aria-current="page">
		{if $shortPageTitle}
			<em>{$shortPageTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}

	</li>
{/strip}