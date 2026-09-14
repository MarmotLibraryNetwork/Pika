{strip}
	{* Cache-busting query string for every css and js file. The embedded list widget page also assigns
	   $listWidgetToken, which rides along so the forward proxy can recognize widget traffic. *}
	{assign var="assetVersion" value=$gitBranch|urlencode}
	{if $listWidgetToken}{assign var="assetVersion" value="`$assetVersion`&`$listWidgetToken`"}{/if}

	{* All CSS should be come before javascript for better browser performance *}
	{if $debugCss}
    {css filename="main.css"}
		{if !$isListWidget}
			<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/jquery.dataTables.css">
		{/if}
	{else}
		{css filename="main.min.css"}
		{if !$isListWidget}
			<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/jquery.dataTables.min.css">
		{/if}
	{/if}

	{if $additionalCss}
		<style>
			{$additionalCss}
		</style>
	{/if}

	{if ($action == 'Covers')}
		<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/dropzone.css">
	{/if}
	{if ($action == 'NovelistInfo')}
		<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/simpleJson.css">
	{/if}

	{* Include all javascript *}
	{if $ie8}
		{* include to give responsive capability to ie8 browsers, but only on successful detection of those browsers. For that reason, don't include in pika.min.js *}
		<script src="/interface/themes/responsive/js/lib/respond.min.js?v={$assetVersion}"></script>
	{/if}
	{if $debugJs}

		<script src="/js/jquery-3.5.1.min.js?v={$assetVersion}"></script>
		{* Load Libraries*}
{*
		{* dropzone *}
		{if ($action == 'Covers')}
		<script src="/interface/themes/responsive/js/lib/dropzone.js"></script>
		{/if}
			{* json-tree *}
		{if ($action == 'NovelistInfo')}
			<script src="/interface/themes/responsive/js/lib/simpleJson.js?v={$assetVersion}"></script>
		{/if}
		{* Validator has two library files *}
		{*<script src="/interface/themes/responsive/js/lib/jquery.validate.js?v={$assetVersion}"></script>*}
		<script src="/interface/themes/responsive/js/lib/jquery.validate.min.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/lib/additional-methods.min.js?v={$assetVersion}"></script>

		{* Combined into ratings.js (part of the pika.min.js)*}
		{*<script src="/interface/themes/responsive/js/lib/rater.min.js"></script>*}
		{*<script src="/interface/themes/responsive/js/lib/rater.js"></script>*}
		<script src="/interface/themes/responsive/js/lib/bootstrap.min.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/lib/jcarousel.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/lib/bootstrap-datepicker.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery-ui-1.13.2.custom.js?v={$assetVersion}"></script>
	{* autocomplete still uses jquery=-ui*}
		<script src="/interface/themes/responsive/js/lib/jquery.touchwipe.min.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.rwdImageMaps.min.js?v={$assetVersion}"></script>
			{* Used for Archive Image maps on Exhibit pages *}

		{* Load application specific Javascript *}
		<script src="/interface/themes/responsive/js/pika/globals.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/base.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/account.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/admin.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/archive.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/archive2.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/browse.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/dpla.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/grouped-work.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/lists.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/lists-widgets.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/log.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/materials-request.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/menu.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/overdrive.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/hoopla.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/prospector.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/ratings.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/reading-history.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/record.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/responsive.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/results-list.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/searches.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/title-scroller.js?v={$assetVersion}"></script>
		<script src="/interface/themes/responsive/js/pika/wikipedia.js?v={$assetVersion}"></script>
		{if !$isListWidget}
			<script src="/interface/themes/responsive/js/lib/jquery.dataTables.js?v={$assetVersion}"></script>
			<script src="/interface/themes/responsive/js/lib/dataTables.bootstrap.js?v={$assetVersion}"></script>
		{/if}
	{else}
		{* This is all merged using the merge_javascript.php file called automatically with a File Watcher*}
		{* Code is minified using uglify.js *}
		<script src="/interface/themes/responsive/js/pika.min.js?v={$assetVersion}"></script>
		{if !$isListWidget}
			<script src="/interface/themes/responsive/js/lib/jquery.dataTables.min.js?v={$assetVersion}"></script>
			<script src="/interface/themes/responsive/js/lib/dataTables.bootstrap.min.js?v={$assetVersion}"></script>
		{/if}
		{if ($action == 'Covers')}
			<script src="/interface/themes/responsive/js/lib/dropzone.min.js?v={$assetVersion}"></script>
		{/if}
		{if ($action == 'NovelistInfo')}
			<script src="/interface/themes/responsive/js/lib/simpleJson.min.js?v={$assetVersion}"></script>
		{/if}
	{/if}

	{/strip}
  <script>
		{* Override variables as needed *}
		{literal}
		$(function(){{/literal}
			Globals.url = '{$url}';
			Globals.loggedIn = {if $loggedIn}true{else}false{/if};
			Globals.opac = {if $onInternalIP}true{else}false{/if};
			Globals.activeModule = '{$module}';
			Globals.activeAction = '{$action}';
			//console.log('{$module}', '{$action}');
			{*Globals.masqueradeMode = {if $masqueradeMode}true{else}false{/if};*}
			{if $encodedRepositoryUrl}
				Globals.encodedRepositoryUrl = '{$encodedRepositoryUrl}';
			{/if}
			{if $debugViewer}
				Globals.debugViewer = true;
				console.log("Setting global setting debugViewer", Globals.debugViewer);
			{/if}

			{if $automaticTimeoutLength}
			Globals.automaticTimeoutLength = {$automaticTimeoutLength};
			{/if}
			{if $automaticTimeoutLengthLoggedOut}
			Globals.automaticTimeoutLengthLoggedOut = {$automaticTimeoutLengthLoggedOut};
			{/if}
			{* Set Search Result Display Mode on Searchbox *}
			{if !$onInternalIP}Pika.Searches.getPreferredDisplayMode();Pika.Archive.getPreferredDisplayMode();{/if}
			{literal}
		});
		{/literal}
	</script>{strip}

	{if $includeAutoLogoutCode == true}
		{if $debugJs}
			<script src="/interface/themes/responsive/js/pika/autoLogout.js?v={$assetVersion}"></script>
		{else}
			<script src="/interface/themes/responsive/js/pika/autoLogout.min.js?v={$assetVersion}"></script>
		{/if}
	{/if}
{/strip}