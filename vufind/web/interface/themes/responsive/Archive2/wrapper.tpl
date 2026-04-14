{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">{$title}</h1>

		{if $can_view == false}  
			{include file="Archive/noAccess.tpl"}
		{else}
			{* start content *}
			{include file="Archive2/$viewer.tpl"}

			<div id="download-options">
			{if $can_download}
				<a class="btn btn-default" href="/Archive/{$pid}/DownloadOriginal">Download Original</a>
			{elseif (!$loggedIn && $allow_original_download)}
					<a class="btn btn-default" onclick="return Pika.Account.followLinkIfLoggedIn(this)" href="/Archive2/{$nid}/DownloadOriginal">Log in to Download Original</a>
			{/if}
			{*if $allowRequestsForArchiveMaterials*}
				<a class="btn btn-default" href="/Archive2/RequestCopy?nid={$nid}">Request Copy</a>
			{*/if*}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive2/ClaimAuthorship?nid={$nid}">Claim Authorship</a>
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
{literal}
<script>
$().ready(function(){
	Pika.Archive2.loadExploreMore({/literal}{$nid}{literal});
});
</script>
{/literal}
