{strip}

	{* In mobile view this is the top div and spans across the screen *}
	{* Logo Div *}
	<div class="d-none d-lg-block col-lg-3 col-xl-3">
		{if !empty($logoLink)}
				<a href="{$logoLink}">
		{else}
				<a href="/">
    {/if}
			<img src="{if $responsiveLogo}{$responsiveLogo}{else}{img filename="logo_responsive.png"}{/if}" alt="Logo for {$librarySystemName}" title="{$logoLinkTitleAttribute}" id="header-logo" {if $showDisplayNameInHeader && $librarySystemName}class="float-start"{/if}>
		</a>
	</div>

	{* Heading Info Div *}
	<div id="headingInfo" class="col-sm-12 col-md-8 col-lg-5 col-xl-5">
		{if $showDisplayNameInHeader && $librarySystemName}
			<p id="library-name-header">{$librarySystemName}</p>
		{/if}

		{if !empty($headerText)}
		<div id="headerTextDiv">{*An id of headerText would clash with the input textarea on the Admin Page*}
			{$headerText}
		</div>
		{/if}

	</div>

	{if !$isUpdatePinPage}
	<div class="logoutOptions"{if !$loggedIn} style="display: none;"{/if}>
		<div class="d-none d-md-block col-md-2 offset-md-5 col-lg-2 offset-lg-0 col-xl-2 offset-xl-0 mx-3">
			<a id="headerMyAccountLink" href="/MyAccount/Home">
				<div class="header-button header-primary">
					{translate text="Your Account"}
			</div>
			</a>
		</div>

		<div class="d-none d-md-block col-md-2 col-lg-2 col-xl-2 mx-3">
			<a id="headerLogoutLink" href="/MyAccount/Logout"{if $masqueradeMode} onclick="return confirm('This will end both Masquerade Mode and your session as well. Continue to log out?')"{/if}>
				<div class="header-button header-primary">
					{translate text="Log Out"}
				</div>
			</a>
		</div>
	</div>

	<div class="loginOptions col-md-2 offset-md-7 col-lg-2 offset-lg-2 offset-xl-2 col-xl-2"{if $loggedIn} style="display: none;"{/if}>
		{if $showLoginButton == 1}
			<a id="headerLoginLink" href="/MyAccount/Home" class="loginLink" data-login="true" title="Login" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this);{/if}">
				<div class="d-none d-md-block header-button header-primary">
					{translate text="LOGIN"}
				</div>
			</a>
		{/if}
	</div>
	{else}
		{* Show log out option on Force Pin Update so users can log out if they choose *}
		<div class="logoutOptions"{if !$loggedIn} style="display: none;"{/if}>
			<div class="d-none d-md-block col-md-2 offset-md-7 col-lg-2 offset-lg-2 offset-xl-2 col-xl-2 mx-3">
				<a  id="headerLogoutLink" href="/MyAccount/Logout"{if $masqueradeMode} onclick="return confirm('This will end both Masquerade Mode and your session as well. Continue to log out?')"{/if}>
					<div class="header-button header-primary">
						{translate text="Log Out"}
					</div>
				</a>
			</div>
		</div>
	{/if}

	{if $topLinks}
		{include file="top-links.tpl"}
	{/if}
{/strip}
