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
			{if $can_download_orginal && $orignal_media_file}
				<a class="btn btn-default" href="{$orignal_media_file}">Download Original File</a>
			{/if}
			{if $can_download_intermediate && $intermediate_media_file}
				<a class="btn btn-default" href="{$intermediate_media_file}">Download Intermediate File</a>
			{/if}
			{if $can_request_copy}
				<a class="btn btn-default" href="/Archive2/RequestCopy/{$nid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive2/ClaimAuthorship/{$nid}">Claim Authorship</a>
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
