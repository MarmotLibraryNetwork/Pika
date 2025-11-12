{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">
			Page Title{*$title*}
		</h1>

		{*if $canView}
			<video width="100%" controls poster="{$medium_image}" id="video-player" oncontextmenu="return false;">
				<source src="{$videoLink}" type="video/mp4">
				{if $vttLink}
				<track kind="captions" src="{$vttLink}" label="English" />
				{/if}
			</video>
		{else}
			{include file="Archive/noAccess.tpl"}
		{/if}

		<div id="download-options">
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$pid}">Claim Authorship</a>
			{/if}
			{if $showFavorites == 1}
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>
			{/if *}
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}