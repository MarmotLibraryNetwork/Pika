{strip}
    {if $showSearchTools || ($loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('contentEditor', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles)))}
			<div class="searchtools card">
			<div class="card-body small">
				<strong>{translate text='Search Tools'}:</strong>
          {if $showSearchTools}
						&nbsp;&nbsp;<a class="text-nowrap" href="{$rssLink|escape}"><span class="bi bi-inbox" aria-hidden="true"></span>&nbsp;{translate text='Get RSS Feed'}</a>
						&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.Account.ajaxLightbox('/Search/AJAX?method=getEmailForm');"><span class="bi bi-envelope" aria-hidden="true"></span>&nbsp;{translate text='Email this Search'}</a>
              {if $savedSearch}
								&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.Account.saveSearch('{$searchId}')"><span class="bi bi-trash" aria-hidden="true"></span>&nbsp;{translate text='save_search_remove'}</a>
              {else}
								&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.Account.saveSearch('{$searchId}')"><span class="bi bi-floppy" aria-hidden="true"></span>&nbsp;{translate text='save_search'}</a>
              {/if}
						&nbsp;&nbsp;<a class="text-nowrap" href="{$excelLink|escape}"><span class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></span>&nbsp;{translate text='Export To Excel'}</a>
          {/if}
          {if $showAdminTools && $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('contentEditor', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles))}
						<br>
						<strong>Admin {translate text='Search Tools'}:</strong>
	          {if $module != 'Archive2'} {* Disable ListWidget button for Archive2 results. *}
						&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.ListWidgets.createWidgetFromSearch('{$searchId}')"><span class="bi bi-card-list" aria-hidden="true"></span>&nbsp;{translate text='Create Widget'}</a>
	          {/if}
						&nbsp;&nbsp;<a class="text-nowrap" href="#" onclick="return Pika.Browse.addToHomePage('{$searchId}', '{$addToHomePageSearchSource|default:"catalog"}')"><span class="bi bi-house" aria-hidden="true"></span>&nbsp;{translate text='Add To Home Page as Browse Category'}</a>
          {/if}
			</div>
			</div>
    {/if}
{/strip}