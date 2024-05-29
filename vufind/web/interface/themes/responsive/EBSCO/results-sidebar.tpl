{strip}
	{* Search Box *}
	{if !$horizontalSearchBar}
		{include file="Search/searchbox-home.tpl"}
	{/if}

	{include file="login-sidebar.tpl"}

	{if $recordCount || $sideRecommendations}
		<div id="refineSearch">

			{include file="Search/results-sort-options.tpl"}

			{* Narrow Results *}
			<div class="row">
				{include file="Search/Recommend/SideFacets.tpl"}
			</div>
		</div>
	{/if}

	{if $loggedIn}
		{* Account Menu *}
		{include file="MyAccount/menu.tpl"}
	{/if}

	{include file="library-sidebar.tpl"}
{/strip}