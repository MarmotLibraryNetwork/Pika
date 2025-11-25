{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">
			{$title}
		</h1>

		{if $canView}
			<video width="100%" controls poster="{$posterUrl}" id="video-player">
				<source src="{$videoUrl}" type="{$videoMime}">
				{if count($captions) >= 1}
					{foreach from=$captions item=i}
						<track kind="captions" src="{$i.fileUrl}" label="{$i.language}" />
					{/foreach}
				
				{/if}
			</video>
		{else}
			{include file="Archive/noAccess.tpl"}
		{/if}
		<button onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>

		{include file="Archive2/metadata.tpl"}
		<div id="download-options">
			{* {if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$pid}">Claim Authorship</a>
			{/if} 
			{if $showFavorites == 1}
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>
			{/if}*}
		</div>

		{*include file="Archive2/metadata.tpl"*}
	</div>
{/strip}