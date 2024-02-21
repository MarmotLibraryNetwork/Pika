<!DOCTYPE html>
<html lang="{$userLang}">{* lang required for Accessibility WCAG 2.1 standard 3.1.1 Language of Page *}
	<head prefix="og: http://ogp.me/ns#">{strip}
		<title>{$pageTitle|truncate:64:"..."}</title>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
			{* Direct Microsoft browsers to use latest rendering engine standards. TODO: likely obsolete *}
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
		{include file="ga4tracking.tpl"}
		{include file="tracking.tpl"}
		{if $google_translate_key}
			<meta name="google-translate-customization" content="{$google_translate_key}">
		{/if}
		{if $google_verification_key}
			<meta name="google-site-verification" content="{$google_verification_key}">
		{/if}

		{if $metadataTemplate}
			{include file=$metadataTemplate}
		{/if}
			<meta property="og:site_name" content="{$librarySystemName|removeTrailingPunctuation|escape:html}">
		{if $og_title}
			<meta property="og:title" content="{$og_title|removeTrailingPunctuation|escape:html}" >
		{/if}
		{if $og_type}
			<meta property="og:type" content="{$og_type|escape:html}">
		{/if}
		{if $og_image}
			<meta property="og:image" content="{$og_image|escape:html}">
		{/if}
		{if $og_url}
			<meta property="og:url" content="{$og_url|escape:html}">
		{/if}
		<link rel="shortcut icon" type="image/x-icon" href="{img filename=favicon.png}">
		<link rel="search" type="application/opensearchdescription+xml" title="{$librarySystemName} Catalog Search" href="/Search/OpenSearch">

		{include file="cssAndJsIncludes.tpl"}
		{/strip}
	</head>
	<body class="module_{$module} action_{$action}{if $masqueradeMode} masqueradeMode{/if}" id="{$module}-{$action}">
		{if $masqueradeMode}
			{include file="masquerade-top-navbar.tpl"}
		{/if}
		{strip}
			<div class="container">
				{if !empty($systemMessage)}
					{if is_array($systemMessage)}
						{foreach from=$systemMessage item=aSystemMessage}
							<div class="row system-message-header">{$aSystemMessage}</div>
					{/foreach}
					{else}
					<div id="system-message-header" class="row system-message-header">{$systemMessage}</div>
				{/if}
			{/if}
			<a id="top"></a>{*TODO: Does anything trigger navigation to page #top? *}
			{if $google_translate_key}
				<div class="row breadcrumbs">
					<div class="col-xs-12 col-sm-3 col-sm-offset-9 text-right">
						<div id="google_translate_element"></div>
					</div>
				</div>
			{/if}

			<div id="header-wrapper" class="row">
				<div id="header-container">
					{include file='header_responsive.tpl'}
				</div>
			</div>

			<div id="horizontal-menu-bar-wrapper" class="row visible-xs">
				<div id="horizontal-menu-bar-container" class="col-tn-12 col-xs-12 menu-bar">
					{include file='horizontal-menu-bar.tpl'}
				</div>
			</div>

		{if !$isUpdatePinPage}
			{if $horizontalSearchBar}
				<div id="horizontal-search-wrapper" class="row">
					<div id="horizontal-search-container" class="col-xs-12">
						{include file="Search/horizontal-searchbox.tpl"}
					</div>
				</div>
			{/if}
		{/if}

			<div id="content-container">
				<div class="row">

					{if !empty($sidebar)} {* Main Content & Sidebars *}

						{if $sideBarOnRight}  {*Sidebar on the right *}
							<div class="rightSidebar col-xs-12 col-sm-4 col-sm-push-8 col-md-3 col-md-push-9 col-lg-3 col-lg-push-9" id="side-bar">
								{include file="sidebar.tpl"}
							</div>
							<div class="rightSidebar col-xs-12 col-sm-8 col-sm-pull-4 col-md-9 col-md-pull-3 col-lg-9 col-lg-pull-3" id="main-content-with-sidebar" style="overflow-x: auto;">
								{* If main content overflows, use a scrollbar *}
								{if $showBreadcrumbs}
									{include file="breadcrumbs.tpl"}
								{/if}
								{if $module}
									{include file="$module/$pageTemplate"}
								{else}
									{include file="$pageTemplate"}
								{/if}
							</div>

						{else} {* Sidebar on the left *}
							<div class="col-xs-12 col-sm-4 col-md-3 col-lg-3" id="side-bar">
								{include file="sidebar.tpl"}
							</div>
							<div class="col-xs-12 col-sm-8 col-md-9 col-lg-9" id="main-content-with-sidebar">
								{if $showBreadcrumbs}
									{include file="breadcrumbs.tpl"}
								{/if}
								{if $module}
									{include file="$module/$pageTemplate"}
								{else}
									{include file="$pageTemplate"}
								{/if}
							</div>
						{/if}

					{else} {* Main Content Only, no sidebar *}
						{if $module}
							{include file="$module/$pageTemplate"}
						{else}
							{include file="$pageTemplate"}
						{/if}
					{/if}
				</div>
			</div>

			<div id="footer-container" class="row">
				{include file="footer_responsive.tpl"}
			</div>

		</div>

		{include file="modal_dialog.tpl"}

			{if $semanticData}
				{include file="jsonld.tpl"}
			{/if}
		{/strip}

		{if $google_translate_key}
			{include file="googleTranslate.tpl"}
		{/if}
	</body>
</html>