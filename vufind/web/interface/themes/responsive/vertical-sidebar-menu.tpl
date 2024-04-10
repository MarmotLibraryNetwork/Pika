{strip}
	{if $displaySidebarMenu}
		<div class="hidden-xs col-sm-1 col-md-1 col-lg-1" id="vertical-menu-bar-wrapper">
			<div id="vertical-menu-bar">
				<div class="menu-bar-option">
					<a href="#" onclick="Pika.Menu.SideBar.showSearch(this)" class="menu-icon" title="Search" id="vertical-menu-search-button">
						<img src="{img filename='/interface/themes/responsive/images/Search.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
						<div class="menu-bar-label rotated-text"><span class="rotated-text-inner">Search</span></div>
					</a>
				</div>
				{if $loggedIn}{* Logged In *}
					<div class="menu-bar-option">
						<a href="#" onclick="Pika.Menu.SideBar.showAccount(this)" class="menu-icon" title="Account">
							<img src="{img filename='/interface/themes/responsive/images/Account.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
							<div class="menu-bar-label rotated-text"><span class="rotated-text-inner">Account</span></div>
						</a>
					</div>
				{else} {* Not Logged In *}
					<div class="menu-bar-option">
						<a href="/MyAccount/Home" id="sidebarLoginLink" onclick="{if $isLoginPage}$('#username').focus();return false{else}return Pika.Account.followLinkIfLoggedIn(this){/if}" data-login="true" class="menu-icon" title="{translate text='Login'}">
							<img src="{img filename='/interface/themes/responsive/images/Login.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
							<div class="menu-bar-label rotated-text"><span class="rotated-text-inner">{translate text='Login'}</span></div>
						</a>
					</div>
				{/if}
				<div class="menu-bar-option">
					<a href="#" onclick="Pika.Menu.SideBar.showMenu(this)" class="menu-icon" title="Additional menu options including links to information about the library and other library resources">
						<img src="{img filename='/interface/themes/responsive/images/Menu.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
						<div class="menu-bar-label rotated-text"><span class="rotated-text-inner">{$sidebarMenuButtonText}</span></div>
					</a>
				</div>
				{if $showExploreMore}
					<div id="sidebar-menu-option-explore-more" class="menu-bar-option">
						<a href="#" onclick="Pika.Menu.SideBar.showExploreMore(this)" class="menu-icon" title="{translate text='Explore More'}">
							<img src="{img filename='/interface/themes/responsive/images/ExploreMore.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
							<div class="menu-bar-label rotated-text">
									<span class="rotated-text-inner">
										{translate text='Explore More'}
									</span>
							</div>
						</a>
					</div>
				{/if}

				{* Open Appropriate Section on Initial Page Load *}
				<script>
					$(function(){ldelim}
						{* .filter(':visible') clauses below ensures that a menu option is triggered if the side bar option is visible is visible :  *}

					{if in_array($action, array('MarcValidations', 'IndexingStats'))}
						{* Select none of the menu options *}
						$(function () {ldelim} Pika.Menu.collapseSideBar(); {rdelim});
					{elseif ($module == "Search" && $action != 'History') || $module == "Series" || $module == "Author" || $module == "Genealogy" || $module == "Library"
							|| ($module == 'MyAccount' && $action == 'MyList' && ($userListHasSearchFilters || !$listEditAllowed))
							|| ($module == 'EBSCO' && $action == 'Results')
							|| ($module == 'Union' && $action == 'CombinedResults')
							|| ($module == 'Archive' && ($action == 'Results' || $action == 'RelatedEntities'))
					    }
								{* Treat Public Lists not owned by user as a Search Page rather than an MyAccount Page *}
								{* Click Search Menu Bar Button *}
							$('.menu-bar-option:nth-child(1)>a', '#vertical-menu-bar').filter(':visible').click();
						{elseif (!$isLoginPage && !in_array($action, array('EmailResetPin', 'ResetPin', 'SelfReg', 'OfflineCirculation', 'MarcValidations', 'CiteList'))) && ($module == "MyAccount" || $module == "Admin" || $module == "Log" || $module == "Circa" || $module == "LibrarianReview" || $module == "Report" || ($module == 'Search' && $action == 'History'))}
							{* Prevent this action on the Pin Reset Page && Login Page && Offline Circulation Page*}
							{* Click Account Menu Bar Button *}
							$('.menu-bar-option:nth-child(2)>a', '#vertical-menu-bar').filter(':visible').click();
						{elseif $showExploreMore}
							{* Click Explore More Menu Bar Button *}
							$('.menu-bar-option:nth-child(4)>a', '#vertical-menu-bar').filter(':visible').click();
						{else}
							{* Click Menu - Sidebar Menu Bar Button *}
							$('.menu-bar-option:nth-child(3)>a', '#vertical-menu-bar').filter(':visible').click();
						{/if}
						{rdelim})
				</script>
			</div>
		</div>
	{/if}
{/strip}