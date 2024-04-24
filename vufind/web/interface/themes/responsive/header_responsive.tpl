{strip}
	<div class="row">

	{* Logo Div *}
	{* In mobile view this div is the top div and spans across the screen *}
	<div class="col-xs-12 col-sm-3 col-md-3 col-lg-3">
		<a href="{$logoLink}/">
			<img src="{if $responsiveLogo}{$responsiveLogo}{else}{img filename="logo_responsive.png"}{/if}" alt="Logo for {$librarySystemName}" title="{$logoImageTagTitleAttribute}" id="header-logo" {if $showDisplayNameInHeader && $librarySystemName}class="pull-left"{/if}>
		</a>
	</div>

	{* Heading Info Div *}
	<div id="headingInfo" class="hidden-xs hidden-sm col-md-5 col-lg-5">
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
	{else}
		{* Show log out option on Force Pin Update so users can log out if they choose *}
		<div class="logoutOptions"{if !$loggedIn} style="display: none;"{/if}>
			<div class="hidden-xs col-sm-2 col-sm-offset-7 col-md-2 col-md-offset-2 col-lg-offset-2 col-lg-2">
				<a  id="headerLogoutLink" href="/MyAccount/Logout"{if $masqueradeMode} onclick="return confirm('This will end both Masquerade Mode and your session as well. Continue to log out?')"{/if}>
					<div class="header-button header-primary">
						{translate text="Log Out"}
					</div>
				</a>
			</div>
		</div>
	{/if}

	</div>

	{if $topLinks}
		<div class="row">
			<div class="col-tn-12" id="header-links">
				{foreach from=$topLinks item=link}
					<div class="header-link-wrapper">
						<a href="{$link->url}" class="library-header-link">{$link->linkText}</a>
					</div>
				{/foreach}
			</div>
		</div>
	{/if}
{/strip}
