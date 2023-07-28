{strip}
	<div class="col-xs-12">
		{* Search Navigation *}
		{include file="Archive/search-results-navigation.tpl"}
		<h2>
			{$title}
			{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
		</h2>
		<div class="row">
			<div id="main-content" class="col-xs-12 text-center">
				{if $canView}
					<div id="view-toggle" class="btn-group" role="group" data-toggle="buttons">
						{if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-pdf" autocomplete="off" onchange="return Pika.Archive.handleBookClick('{$pid}', Pika.Archive.activeBookPage, 'pdf');">
							{*TODO: set bookPID*}

							View As PDF
						</label>
						{/if}
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-image" autocomplete="off" onchange="return Pika.Archive.handleBookClick('{$pid}', Pika.Archive.activeBookPage, 'image');">

							View As Image
						</label>
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-transcription" autocomplete="off" onchange="return Pika.Archive.handleBookClick('{$pid}', Pika.Archive.activeBookPage, 'transcription');">

							View Transcription
						</label>
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-audio" autocomplete="off" onchange="return Pika.Archive.handleBookClick('{$pid}', Pika.Archive.activeBookPage, 'audio');">

							Listen to Audio
						</label>
						<label class="btn btn-group-small btn-default">
							<input type="radio" name="pageView" id="view-toggle-video" autocomplete="off" onchange="return Pika.Archive.handleBookClick('{$pid}', Pika.Archive.activeBookPage, 'video');">

							Watch Video
						</label>
					</div>

					<br>

					<div id="view-pdf" width="100%" height="600px" style="display: none">
						No PDF loaded
					</div>

					<div id="view-image" style="display: none">
						<div class="large-image-wrapper">
							<div class="large-image-content" oncontextmenu="return false;">
								<div id="pika-openseadragon" class="openseadragon"></div>
							</div>
						</div>
					</div>

					<div id="view-transcription" style="display: none" width="100%" height="600px;">
						No transcription loaded
					</div>

					<div id="view-audio" style="display: none">
						<img src="" class="img-responsive">
						<audio controls id="audio-player" oncontextmenu="return false;">
							<source src="" type="audio/mpeg" id="audio-player-src">
						</audio>
					</div>

					<div id="view-video" style="display: none">
						<video width="100%" controls poster="" id="video-player" oncontextmenu="return false;">
							<source src="" type="video/mp4" id="video-player-src">
						</video>
					</div>

				{else}
					{include file="Archive/noAccess.tpl"}
				{/if}
			</div>
		</div>

		<div id="download-options">
			{*
			<a class="btn btn-default" href="/Archive/{$pid}/DownloadPDF">Download Book As PDF</a>
			<a class="btn btn-default" href="/Archive/{$activePage}/DownloadPDF" id="downloadPageAsPDF">Download Page As PDF</a>
			*}
			<br>
			{if $hasPdf && ($anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload))}
				<a class="btn btn-default" href="/Archive/{$pid}/DownloadPDF">Download PDF</a>
			{elseif ($hasPdf && !$loggedIn && $verifiedMasterDownload)}
				<a class="btn btn-default" onclick="return Pika.Account.followLinkIfLoggedIn(this)" href="/Archive/{$pid}/DownloadPDF">Log in to Download PDF</a>
			{/if}
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

		{if $canView}
			<div class="row">
				<div class="col-xs-12 text-center">
					<div class="jcarousel-wrapper" id="book-sections">
						<a href="#" class="jcarousel-control-prev"{* data-target="-=1"*}><i class="glyphicon glyphicon-chevron-left"></i></a>
						<a href="#" class="jcarousel-control-next"{* data-target="+=1"*}><i class="glyphicon glyphicon-chevron-right"></i></a>

						<div class="relatedTitlesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
							<ul>
								{assign var=pageCounter value=1}
								{foreach from=$bookContents item=section}
									{if count($section.pages) == 0}
										<li class="relatedTitle">
											<a href="{$section.link}?pagePid={$section.pid}" onclick="return Pika.Archive.handleBookClick('{$pid}', '{$section.pid}', Pika.Archive.activeBookViewer);">
												<figure class="thumbnail">
													<img src="{$section.cover}" alt="{$section.title|removeTrailingPunctuation|truncate:80:"..."}">
													<figcaption>{$section.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
												</figure>
											</a>
										</li>
										{assign var=pageCounter value=$pageCounter+1}
									{else}
										{foreach from=$section.pages item=page}
											<li class="relatedTitle">
												<a href="{$page.link}?pagePid={$page.pid}" onclick="return Pika.Archive.handleBookClick('{$pid}', '{$page.pid}', Pika.Archive.activeBookViewer);">
													<figure class="thumbnail">
														<img src="{$page.cover}" alt="Page {$pageCounter}">
														<figcaption>{$pageCounter}</figcaption>
													</figure>
												</a>
											</li>
											{assign var=pageCounter value=$pageCounter+1}
										{/foreach}
									{/if}
								{/foreach}
							</ul>
						</div>
					</div>
				</div>
			</div>
		{/if}

		{include file="Archive/metadata.tpl"}
	</div>
{/strip}
<script src="/js/openseadragon/openseadragon.js" ></script>
<script src="/js/openseadragon/djtilesource.js" ></script>
{if $canView}
<script type="text/javascript">
	{if !($anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload))}
		Pika.Archive.allowPDFView = false;
	{/if}
	{assign var=pageCounter value=1}
	{foreach from=$bookContents item=section}
		Pika.Archive.pageDetails['{$section.pid}'] = {ldelim}
			pid: '{$section.pid}',
			title: "{$section.title|escape:javascript}",
			pdf: {if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}'{$section.pdf}'{else}''{/if},
			jp2: '',
			video: '{$section.video}',
			audio: '{$section.audio}',
			cover: '{$section.cover}',
			transcript: '{$section.transcript}',
		{rdelim};

		{foreach from=$section.pages item=page}
			Pika.Archive.pageDetails['{$page.pid}'] = {ldelim}
				pid: '{$page.pid}',
				title: 'Page {$pageCounter}',
				pdf: {if $anonymousMasterDownload || ($loggedIn && $verifiedMasterDownload)}'{$page.pdf}'{else}''{/if},
				jp2: '{$page.jp2}',
				transcript: '{$page.transcript}',
				video: '{$page.video}',
				audio: '{$page.audio}',
				index: '{$pageCounter}'
			{rdelim};
			{if $page.pid == $activeObj}
				{assign var=scrollToPage value=$pageCounter}
				Pika.Archive.curPage = {$pageCounter};
			{/if}
			{assign var=pageCounter value=$pageCounter+1}
		{foreachelse}
			{* Increment page counter once for the new section even if it's empty*}
			{assign var=pageCounter value=$pageCounter+1}
		{/foreach}
	{/foreach}

	{* Greater than 2 because we increment page counter after an object is displayed *}
	{if $pageCounter > 2}
		Pika.Archive.multiPage = true;
	{/if}

	$(function(){ldelim}
		{* Below click events trigger indirectly the handleBookClick function, and properly sets the appropriate button. *}
		Pika.Archive.handleBookClick('{$pid}', '{$activePage}', '{$activeViewer}');

	{rdelim});
</script>
{/if}
<script type="text/javascript">
	$().ready(function(){ldelim}
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>