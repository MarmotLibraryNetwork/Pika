<!DOCTYPE html>
<html lang="{$userLang}" class="embeddedListWidget">
{strip}
<head>
	<title>{$widget->name}</title>
  <meta http-equiv="Content-Type" content="text/html;charset=utf-8">

	{include file="cssAndJsIncludes.tpl" includeAutoLogoutCode=false}
	{*TODO a smaller suite of javascript for List Widgets*}

	{if $resizeIframe}
	<script src="/js/iframeResizer/iframeResizer.contentWindow.min.js"></script>
	{/if}

  {if $widget->customCss}
  	<link rel="stylesheet" type="text/css" href="{$widget->customCss}">
  {/if}
  <base href="" target="_parent">{* Sets the default target of all links in the list widget to the parent page *}
</head>

<body class="embeddedListWidgetBody">
	<div class="container-fluid">
		{include file='ListWidget/listWidgetTabs.tpl'}
  </div>
</body>
</html>
{/strip}