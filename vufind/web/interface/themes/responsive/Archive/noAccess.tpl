{strip}
	<div class="alert alert-warning">
		{if $archiveOnlyInterface}
			<p>Sorry you don't have access to this object.  You must access the catalog from a computer at {if $access_restricted_library_name}{$access_restricted_library_name|escape}{else}the library{/if}.</p>
		{else}
			<p>Sorry you don't have access to this object.  You must {if $access_restricted_library_name}log in as a patron of {$access_restricted_library_name|escape}, or access the catalog from a computer at {$access_restricted_library_name|escape}{else}log in or access the catalog from a computer at the library{/if}.</p>
			<a href="/MyAccount/Home" class="btn btn-default loginLink" data-login="true" title="{translate text='Login'}" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this);{/if}">{translate text='Log In'}</a>
		{/if}
	</div>
{/strip}