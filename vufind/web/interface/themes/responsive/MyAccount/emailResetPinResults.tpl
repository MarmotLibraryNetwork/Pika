{strip}
	<div id="main-content">
		<h1 role="heading" aria-level="1" class="h2">{translate text='Reset My PIN'}</h1>
		{if $emailResult.error}
			<p class="alert alert-danger">{$emailResult.error}</p>
			<div>
				<a class="btn btn-primary" role="button" href="/MyAccount/EmailResetPin">Try Again</a>
			</div>
		{else}
			<p class="alert alert-success">
				An email has been sent to the email address associated with your account containing a link to reset {* preserve trailing space*}
				your {translate text='pin'}.
			</p>
			<p class="alert alert-warning">
				If you do not receive an email within a few minutes, please check any spam folder your email service may {* preserve trailing space*}
				&nbsp;have. If you do not receive an email, please contact your library to have them reset {* preserve trailing space*}
				your {translate text='pin'}.
			</p>
			<p>
				<a class="btn btn-primary" role="button" href="/MyAccount/Login">{translate text='Login'}</a>
			</p>
		{/if}
	</div>
{/strip}
