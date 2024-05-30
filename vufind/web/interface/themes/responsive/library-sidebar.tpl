{strip}
	<div id="home-page-library-section" class="row"{if $displaySidebarMenu} style="display: none"{/if}>
		{if $showLibraryHoursAndLocationsLink & (!isset($numHours) || $numHours > 0)} {*Don't render link if there won't be any text displayed (Accessibility: Links must have discernable text)*}
			<a href="/AJAX/JSON?method=getHoursAndLocations" data-title="Library Hours and Locations" class="modalDialogTrigger">
				<div id="home-page-hours-locations" class="sidebar-button">
            {if !isset($numHours) || $numHours > 0}Library Hours{/if}{if (!isset($numHours) || $numHours > 0) && $numLocations != 1} &amp; {/if}{if $numLocations != 1}Locations{/if}
				</div>
			</a>
		{/if}

		{if $homeLink}
			<a href="{$homeLink}">
				<div id="home-page-home-button" class="sidebar-button">
					Library Home Page
				</div>
			</a>
		{/if}

		{include file="library-links.tpl" libraryLinks=$libraryHelpLinks linksId='home-library-links' section='Help'}
	</div>
{/strip}