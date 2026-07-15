{strip}
	<li class="breadcrumb-item">
		<a href="/MyAccount/Home">{translate text='Your Account'}</a>
	</li>
	<li class="breadcrumb-item active" aria-current="page">
		{if $pageTitle}
			<em>{$pageTitle}</em>
		{else}
			<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
		{/if}

	</li>
{/strip}