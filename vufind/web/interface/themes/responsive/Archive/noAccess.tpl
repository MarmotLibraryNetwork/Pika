{strip}
	<div class="alert alert-warning">
		{if $archiveOnlyInterface}
			<p>Sorry you don't have access to this object.  You must access the catalog from a computer at the library.</p>
		{else}
			<p>Sorry you don't have access to this object.  You must log in or access the catalog from a computer at the library.</p>
			<a href="/MyAccount/Home" class="btn btn-default loginLink" data-login="true" title="{translate text='Login'}" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this);{/if}">{translate text='Log In'}</a>
		{/if}
	</div>
{/strip}