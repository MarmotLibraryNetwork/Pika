{strip}
	{if $showBreadcrumbs}
		{include file="breadcrumbs.tpl"}
	{/if}
	<main id="main">
		{if $module}
			{include file="$module/$pageTemplate"}
		{else}
			{include file="$pageTemplate"}
		{/if}
	</main>
{/strip}