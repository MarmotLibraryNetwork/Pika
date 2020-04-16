{strip}
    {if $showSearchTools || ($loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('contentEditor', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles)))}
			<div class="searchtools well small">
				<strong>{translate text='Search Tools'}:</strong>
          {if $showSearchTools}
						&nbsp;&nbsp;<a href="{$rssLink|escape}"><span class="glyphicon glyphicon-inbox" aria-hidden="true"></span>&nbsp;{translate text='Get RSS Feed'}</a>
						&nbsp;&nbsp;<a href="#" onclick="return Pika.Account.ajaxLightbox('/Search/AJAX?method=getEmailForm');"><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>&nbsp;{translate text='Email this Search'}</a>
              {if $savedSearch}
								&nbsp;&nbsp;<a href="#" onclick="return Pika.Account.saveSearch('{$searchId}')"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span>&nbsp;{translate text='save_search_remove'}</a>
              {else}
								&nbsp;&nbsp;<a href="#" onclick="return Pika.Account.saveSearch('{$searchId}')"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>&nbsp;{translate text='save_search'}</a>
              {/if}
						&nbsp;&nbsp;<a href="{$excelLink|escape}"><span class="glyphicon glyphicon-th" aria-hidden="true"></span>&nbsp;{translate text='Export To Excel'}</a>
          {/if}
          {if $showAdminTools && $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('contentEditor', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles))}
						<br>
						<strong>Admin {translate text='Search Tools'}:</strong>
						&nbsp;&nbsp;<a href="#" onclick="return Pika.ListWidgets.createWidgetFromSearch('{$searchId}')"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span>&nbsp;{translate text='Create Widget'}</a>
						&nbsp;&nbsp;<a href="#" onclick="return Pika.Browse.addToHomePage('{$searchId}')"><span class="glyphicon glyphicon-home" aria-hidden="true"></span>&nbsp;{translate text='Add To Home Page as Browse Category'}</a>
          {/if}
			</div>
    {/if}
{/strip}