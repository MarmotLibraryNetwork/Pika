{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h1 role="heading" aria-level="1" class="h2">
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h1>
		<div class="row">
			<div id="main-content" class="col-xs-12 text-center">
				{if $canView}
					<div id="view-toggle" class="btn-group" role="group" data-toggle="buttons">
						{if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-pdf" autocomplete="off" onchange="Pika.Archive.changeActiveBookViewer('pdf', Pika.Archive.activeBookPage);">
							View As PDF
						</label>
						{/if}
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-image" autocomplete="off" onchange="Pika.Archive.changeActiveBookViewer('image', Pika.Archive.activeBookPage);">
							View As Image
						</label>
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-transcription" autocomplete="off" onchange="Pika.Archive.changeActiveBookViewer('transcription', Pika.Archive.activeBookPage);">
							View Transcription
						</label>
					</div>

					<div id="view-pdf" width="100%" height="600px">
						No PDF loaded
					</div>

					<div id="view-image" style="display: none">
						<div class="large-image-wrapper">
							<div class="large-image-content">
								<div id="pika-openseadragon" class="openseadragon"></div>
							</div>
						</div>
					</div>

					<div id="view-transcription" style="display: none" width="100%" height="600px;">
						No transcription loaded
					</div>
				{else}
					{include file="Archive/noAccess.tpl"}
				{/if}
			</div>
		</div>

		<div id="download-options">
			{if $allowRequestsForArchiveMaterials}
				<a class="btn btn-default" href="/Archive/RequestCopy?pid={$pid}">Request Copy</a>
			{/if}
			{if $showClaimAuthorship}
				<a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$pid}">Claim Authorship</a>
			{/if}
			{if $showFavorites == 1}
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$pid|escape}');" class="btn btn-default ">{translate text='Add to favorites'}</button>
			{/if}
		</div>

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script src="/js/openseadragon/openseadragon.js" ></script>
<script src="/js/openseadragon/djtilesource.js" ></script>

<script>
	{if !($anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload))}
	Pika.Archive.allowPDFView = false;
	{/if}
	{assign var=pageCounter value=1}
	Pika.Archive.pageDetails['{$page.pid}'] = {ldelim}
		pid: '{$page.pid}',
		pdf: {if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}'{$page.pdf}'{else}''{/if},
		jp2: '{$page.jp2}',
		transcript: '{$page.transcript}'
	{rdelim};
	{assign var=pageCounter value=$pageCounter+1}

	$(function(){ldelim}
		{if $canView}
		Pika.Archive.changeActiveBookViewer('{$activeViewer}', '{$page.pid}')
		{/if}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>
