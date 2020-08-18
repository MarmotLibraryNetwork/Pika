{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h2>
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h2>

		{if $canView}
			<img src="{$medium_image}" class="img-responsive">
			<audio width="100%" controls id="player" class="copy-prevention" oncontextmenu="return false;">
				<source src="{$audioLink}" type="audio/mpeg">
			</audio>

		{else}
			{include file="Archive/noAccess.tpl"}
		{/if}
		<div id="download-options">
			{* {if $canView}
				{if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}
					<a class="btn btn-default" href="/Archive/{$pid}/DownloadOriginal">Download Original</a>
				{elseif (!$loggedIn && $verifiedMasterDownload)}
					<a class="btn btn-default" onclick="return Pika.Account.followLinkIfLoggedIn(this)" href="/Archive/{$pid}/DownloadOriginal">Log in to Download Original</a>
				{/if}
			{/if} *}
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$pid}">Claim Authorship</a>
			{/if}
			{if $showFavorites == 1}
				<a onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default ">{translate text='Add to favorites'}</a>
			{/if}
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script type="text/javascript">
	$().ready(function(){ldelim}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
		{rdelim});
	{literal}
	$(document).ready(function() {
		let audio = document.getElementById("player");
		audio.addEventListener('play', function(ev){

			$.idleTimer('destroy');
		});
		audio.addEventListener('pause', function(ev){
			var timeout;
			if (Globals.loggedIn){
				timeout = Globals.automaticTimeoutLength * 1000;
			}else{
				timeout = Globals.automaticTimeoutLengthLoggedOut * 1000;
			}
			if (timeout > 0){
				$.idleTimer(timeout); // start the Timer
			}

			$(document).on("idle.idleTimer", function(){
				$.idleTimer('destroy'); // turn off Timer, so that when it is re-started in will work properly
				if (Globals.loggedIn){
					showLogoutMessage();
				}else{
					showRedirectToHomeMessage();
				}
			});
		});
	});
	{/literal}
</script>