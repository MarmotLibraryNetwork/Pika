{strip}
	<li class="breadcrumb-item">
		<a href="/MyAccount/Home">{translate text='Your Account'}</a>
	</li>
	<li class="breadcrumb-item">
		{if $shortPageTitle}
			<em>{$shortPageTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}

	</li>
{/strip}