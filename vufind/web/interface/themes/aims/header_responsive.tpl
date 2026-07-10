<div class="row">
	<div id="header_black" class="col-12">
		<a href="https://www.aims.edu/">
			<img src="/interface/themes/aims/images/logo_responsive.png" alt="Aims Community College">
		</a>
	</div>
</div>
<div class="row">
	<div id="header_library">
		<div class="col-4 col-sm-4 col-md-3 col-lg-8 col-xl-8">
			<a class="nielsenlibrarytxt" href="https://www.aims.edu/kieferlibrary/">KIEFER LIBRARY</a>
		</div>

		<div class="logoutOptions"{if !$loggedIn} style="display: none;"{/if}>
			<div class="d-sm-none d-md-block col-md-2 offset-md-5 col-lg-2 offset-lg-0 col-xl-2 offset-xl-0">
				<a id="headerMyAccountLink" href="/MyAccount/Home">
					<div class="header-button header-primary">
						{translate text="Your Account"}
					</div>
				</a>
			</div>

			<div class="d-sm-none d-md-block col-md-2 col-lg-2 col-xl-2">
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
					<div class="d-sm-none d-md-block header-button header-primary">
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