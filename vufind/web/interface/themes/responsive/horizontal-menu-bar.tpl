{strip}
	{* Mobile Horizontal Menu *}
	{if $loggedIn}{* Logged In *}
		<a href="/MyAccount/Logout" id="mobileLogoutLink" class="menu-icon" title="{translate text="Log Out"}">
			<img src="{img filename='/interface/themes/responsive/images/Logout.png'}" alt="{translate text="Log Out"}">
		</a>
		{if !$isUpdatePinPage}
			<a href="#{*home-page-login*}" id="mobile-menu-account-icon" onclick="Pika.Menu.Mobile.showAccount(this)" class="menu-icon" title="Account">
				<img src="{img filename='/interface/themes/responsive/images/Account.png'}" alt="Account">
			</a>
		{/if}
	{else} {* Not Logged In *}
		<a href="/MyAccount/Home" id="mobileLoginLink" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this){/if}" data-login="true" class="menu-icon" title="{translate text='Login'}">
			{*<img src="{img filename='/interface/themes/responsive/images/Account.png'}" alt="{translate text='Login'}">*}
			<img src="{img filename='/interface/themes/responsive/images/Login.png'}" alt="{translate text='Login'}">
		</a>
	{/if}
	{if !$isUpdatePinPage}
		<a href="#{*home-page-login*}" id="mobile-menu-menu-icon" onclick="Pika.Menu.Mobile.showMenu(this)" class="menu-icon" title="Menu">
			<img src="{img filename='/interface/themes/responsive/images/Menu.png'}" alt="Menu">
		</a>
	{/if}

		{if !$horizontalSearchBar}
			{*Only display this icon when we are using the sidebar searchbox, and not the horizontal search box
			 since collapsing the horizontal search box has several complications *}
	<a href="#{*horizontal-menu-bar-wrapper*}" id="mobile-menu-search-icon" onclick="Pika.Menu.Mobile.showSearch(this)" class="menu-icon menu-left" title="Search">
		{* mobile-menu-search-icon id used by Refine Search button to set the menu to search (in case another menu option has been selected) *}
		<img src="{img filename='/interface/themes/responsive/images/Search.png'}" alt="Search">
	</a>
		{/if}


		{if $showExploreMore}
		{* TODO: set explore more anchor tag so exploremore is moved into view on mobile *}
		<a href="#" id="mobile-menu-explore-more-icon" onclick="Pika.Menu.Mobile.showExploreMore(this)" class="menu-icon menu-left" title="{translate text='Explore More'}">
			<img src="{img filename='/interface/themes/responsive/images/ExploreMore.png'}" alt="{translate text='Explore More'}">
		</a>
	{/if}
{/strip}