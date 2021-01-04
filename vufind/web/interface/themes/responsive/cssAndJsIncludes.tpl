{strip}
	{* All CSS should be come before javascript for better browser performance *}
	{if $debugCss}
    {css filename="main.css"}
		<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/dataTables.css">
	{else}
		{css filename="main.min.css"}
		<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/dataTables.bootstrap.min.css">
	{/if}
	{if $additionalCss}
		<style type="text/css">
			{$additionalCss}
		</style>
	{/if}
	<link rel="stylesheet" type="text/css" href="/interface/themes/responsive/css/lib/dropzone.css">

	{* Include correct all javascript *}
	{if $ie8}
		{* include to give responsive capability to ie8 browsers, but only on successful detection of those browsers. For that reason, don't include in pika.min.js *}
		<script src="/interface/themes/responsive/js/lib/respond.min.js?v={$gitBranch|urlencode}"></script>
	{/if}
	{if $debugJs}

		<script src="/js/jquery-3.5.1.min.js?v={$gitBranch|urlencode}"></script>
		{* Load Libraries*}
{*		<script src="/interface/themes/responsive/js/lib/jquery.tablesorter.js?v={$gitBranch|urlencode}"></script>*}
		<script src="/interface/themes/responsive/js/lib/jquery.tablesorter.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.tablesorter.pager.min.js?v={$gitBranch|urlencode}"></script>
{*		<script src="/interface/themes/responsive/js/lib/jquery.tablesorter.widgets.js?v={$gitBranch|urlencode}"></script>*}
		<script src="/interface/themes/responsive/js/lib/jquery.tablesorter.widgets.min.js?v={$gitBranch|urlencode}"></script>
		{* dropzone *}

		<script src="/interface/themes/responsive/js/lib/dropzone.js"></script>
		{* Validator has two library files *}
		{*<script src="/interface/themes/responsive/js/lib/jquery.validate.js?v={$gitBranch|urlencode}"></script>*}
		<script src="/interface/themes/responsive/js/lib/jquery.validate.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/additional-methods.min.js?v={$gitBranch|urlencode}"></script>

		<script src="/interface/themes/responsive/js/lib/recaptcha_ajax.js?v={$gitBranch|urlencode}"></script>
		{* Combined into ratings.js (part of the pika.min.js)*}
		{*<script src="/interface/themes/responsive/js/lib/rater.min.js"></script>*}
		{*<script src="/interface/themes/responsive/js/lib/rater.js"></script>*}
		<script src="/interface/themes/responsive/js/lib/bootstrap.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jcarousel.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/bootstrap-datepicker.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery-ui-1.12.1.custom.min.js?v={$gitBranch|urlencode}"></script>
{*		<script src="/interface/themes/responsive/js/lib/jquery-ui-1.10.4.custom.min.js?v={$gitBranch|urlencode}"></script>*}
	{* autocomplete still uses jquery=-ui*}
		<script src="/interface/themes/responsive/js/lib/bootstrap-switch.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.touchwipe.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.rwdImageMaps.min.js?v={$gitBranch|urlencode}"></script>
			{* Used for Archive Image maps on Exhibit pages *}

		{* Load application specific Javascript *}
		<script src="/interface/themes/responsive/js/pika/globals.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/base.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/account.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/admin.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/archive.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/browse.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/dpla.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/grouped-work.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/lists.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/lists-widgets.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/log.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/materials-request.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/menu.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/overdrive.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/hoopla.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/prospector.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/ratings.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/reading-history.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/record.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/responsive.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/results-list.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/searches.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/title-scroller.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/pika/wikipedia.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.dataTables.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/dataTables.bootstrap.js?v={$gitBranch|urlencode}"></script>
	{else}
		{* This is all merged using the merge_javascript.php file called automatically with a File Watcher*}
		{* Code is minified using uglify.js *}
		<script src="/interface/themes/responsive/js/pika.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/dropzone.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/jquery.dataTables.min.js?v={$gitBranch|urlencode}"></script>
		<script src="/interface/themes/responsive/js/lib/dataTables.bootstrap.min.js?v={$gitBranch|urlencode}"></script>
		{*<script src="/interface/themes/responsive/js/pika.min.js?v={$gitBranch|urlencode}"></script>*}
	{/if}

	{/strip}
  <script type="text/javascript">
		{* Override variables as needed *}
		{literal}
		$(document).ready(function(){{/literal}
			Globals.path = '{$path}';
			Globals.url = '{$url}';
			Globals.loggedIn = {if $loggedIn}true{else}false{/if};
			Globals.opac = {if $onInternalIP}true{else}false{/if};
			Globals.activeModule = '{$module}';
			Globals.activeAction = '{$action}';
			{*Globals.masqueradeMode = {if $masqueradeMode}true{else}false{/if};*}
			{if $repositoryUrl}
				Globals.repositoryUrl = '{$repositoryUrl}';
				Globals.encodedRepositoryUrl = '{$encodedRepositoryUrl}';
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
			<script type="text/javascript" src="/interface/themes/responsive/js/pika/autoLogout.js?v={$gitBranch|urlencode}"></script>
		{else}
			<script type="text/javascript" src="/interface/themes/responsive/js/pika/autoLogout.min.js?v={$gitBranch|urlencode}"></script>
		{/if}
	{/if}
{/strip}