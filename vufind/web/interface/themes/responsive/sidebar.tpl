{strip}
	<div class="row" id="vertical-menu-bar-container">
		{if !$sideBarOnRight}
			{include file="vertical-sidebar-menu.tpl"}
		{/if}

		<div class="col-sm-12{if $displaySidebarMenu} col-md-10 col-lg-10 col-xl-10{/if}" id="sidebar-content">
			{* Full Column width *}
			{include file="$sidebar"}
		</div>

		{if $sideBarOnRight}
			{include file="vertical-sidebar-menu.tpl"}
		{/if}

	</div>
{/strip}