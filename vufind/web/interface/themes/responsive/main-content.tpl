{strip}
	{if $showBreadcrumbs}
		{include file="breadcrumbs.tpl"}
	{/if}
	{* tabindex="-1" keeps <main> out of the normal Tab order but lets the
	   "Skip to main content" link (href="#main") move keyboard focus into it;
	   without it the anchor jump scrolls but leaves focus on the link. *}
	<main id="main" tabindex="-1">
		{if $module}
			{include file="$module/$pageTemplate"}
		{else}
			{include file="$pageTemplate"}
		{/if}
	</main>
{/strip}