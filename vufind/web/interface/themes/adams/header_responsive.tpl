
<div class="row">
	<div id="header_library">
		<div class="col-tn-4 col-xs-4 col-sm-3 col-md-8 col-lg-8">
			<a href="{if !empty($logoLink)}{$logoLink}{else}/{*empty link to home page*}{/if}" title="{$logoLinkTitleAttribute}">
				<img id="header-logo" class="img-fluid" src="{if $responsiveLogo}{$responsiveLogo}{else}{img filename="logo_responsive.png"}{/if}" alt="Logo for {$librarySystemName}" {if $showDisplayNameInHeader && $librarySystemName}class="pull-left"{/if}>
			</a>
		</div>

		<div class="logoutOptions"{if !$loggedIn} style="display: none;"{/if}>
			<div class="hidden-xs col-sm-2 col-sm-offset-5 col-md-2 col-md-offset-0 col-lg-2 col-lg-offset-0">
				<a id="headerMyAccountLink" href="/MyAccount/Home">
					<div class="header-button header-primary">
						{translate text="Your Account"}
					</div>
				</a>
			</div>

			<div class="hidden-xs col-sm-2 col-md-2 col-lg-2">
				<a id="headerLogoutLink" href="/MyAccount/Logout"{if $masqueradeMode} onclick="return confirm('This will end both Masquerade Mode and your session as well. Continue to log out?')"{/if}>
					<div class="header-button header-primary">
						{translate text="Log Out"}
					</div>
				</a>
			</div>
		</div>

		<div class="loginOptions col-sm-2 col-sm-offset-7 col-md-2 col-md-offset-2 col-lg-offset-2 col-lg-2"{if $loggedIn} style="display: none;"{/if}>
			{if $showLoginButton == 1}
				<a id="headerLoginLink" href="/MyAccount/Home" class="loginLink" data-login="true" title="Login" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this);{/if}">
					<div class="hidden-xs header-button header-primary">
						{translate text="LOGIN"}
					</div>
				</a>
			{/if}
		</div>

	</div>
</div>

{strip}
	{if $topLinks}
		{include file="top-links.tpl"}
	{/if}
{/strip}