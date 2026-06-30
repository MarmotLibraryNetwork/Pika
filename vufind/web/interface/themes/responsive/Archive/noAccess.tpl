{strip}
	<div class="alert alert-warning">
		{if $content_unavailable}
			{* pika_usage took this object out of circulation (set to "no", or "testonly" on
			   production) -- this is not a pika_access_limits library restriction, so no
			   library/login messaging applies; nothing the patron does will grant access. *}
			<p>Sorry, this object is not currently available.</p>
		{elseif $archiveOnlyInterface}
			<p>Sorry you don't have access to this object.  You must access the catalog from a computer at {if $access_restricted_library_name}{$access_restricted_library_name|escape}{else}the library{/if}.</p>
		{else}
			<p>Sorry you don't have access to this object.  You must {if $access_restricted_library_name}log in as a patron of {$access_restricted_library_name|escape}, or access the catalog from a computer at {$access_restricted_library_name|escape}{else}log in or access the catalog from a computer at the library{/if}.</p>
			<a href="/MyAccount/Home" class="btn btn-default loginLink" data-login="true" title="{translate text='Login'}" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this);{/if}">{translate text='Log In'}</a>
		{/if}
	</div>
{/strip}