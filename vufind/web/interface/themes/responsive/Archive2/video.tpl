{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}  
			{include file="Archive/noAccess.tpl"}
		{else}
			{* start content *}
			<video width="100%" controls poster="{$posterUrl}" id="video-player">
				<source src="{$videoUrl}" type="{$videoMime}">
				{if count($captions) >= 1}
                    {foreach from=$captions item=i}
                        <track kind="captions" src="/Archive/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}" label="{$i.language}" srclang="{$i.langcode}" />
                    {/foreach}
                {/if}
			</video>

			<div id="download-options">
			{if $can_download}
				<a class="btn btn-default" href="/Archive/{$pid}/DownloadOriginal">Download Original</a>
			{elseif (!$loggedIn && $allow_original_download)}
					<a class="btn btn-default" onclick="return Pika.Account.followLinkIfLoggedIn(this)" href="/Archive/{$pid}/DownloadOriginal">Log in to Download Original</a>
			{/if}
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$nid}">Request Copy</a>				`				`
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$nid}">Claim Authorship</a>
			{/if} 
			{if $showFavorites == 1}
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$nid|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>
			{/if}
			</div>

			{include file="Archive2/metadata.tpl"}
		</div>
		{* end content *}
	{/if} 
{/strip}