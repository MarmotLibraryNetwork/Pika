{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h1>

		{if $canView}
			<img src="{$medium_image}" class="img-responsive">
			<audio controls id="audio-player" oncontextmenu="return false;">
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
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>
			{/if}
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script>
		{literal}
		$(function(){
		Pika.Archive.loadExploreMore('{/literal}{$pid}{literal}');

		let audio = document.getElementById("audio-player");
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
		if(document.getElementById("video-player")){
			let video = document.getElementById("video-player");
			video.addEventListener('play', function(ev){
				$.idleTimer('destroy');
			});
			video.addEventListener('pause', function(ev){
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
		}
	});
	{/literal}
</script>