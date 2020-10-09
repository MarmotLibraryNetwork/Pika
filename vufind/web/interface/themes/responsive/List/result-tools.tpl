<div class="result-tools-horizontal btn-toolbar" role="toolbar">
	<div class="btn-group btn-group-sm">
		{if $showMoreInfo !== false}
		<a href="/MyAccount/MyList/{$summShortId}" class="btn btn-sm">More Info</a>
		{/if}
	</div>
	<div class="btn-group btn-group-sm">

			<a href="#" onclick="return  Pika.Lists.copyList({$summShortId})" class="btn btn-sm">Copy List</a>

	</div>

		{if $showEmailThis == 1 || $showShareOnExternalSites == 1}
			<div class="btn-group btn-group-sm">
					<div class="share-tools" >
						<span class="share-tools-label hidden-inline-xs">SHARE LIST</span>
						<a herf="#" onclick="return Pika.Lists.emailListAction({$summShortId})" title="share via e-mail">
							<img src="{img filename='email-icon.png'}" alt="E-mail this" style="cursor:pointer;">
						</a>
						<a href="#" id="FavExcel" onclick="return Pika.Lists.exportListFromLists('{$summShortId}');" title="Export List to Excel">
							<img src="{img filename='excel.png'}" alt="Export to Excel" />
						</a>
						<a href="https://twitter.com/compose/tweet?text={$recordDriver->getTitle()|urlencode}+{$url|escape:"html"}/MyAccount/MyList/{$summShortId}" target="_blank" title="Share on Twitter">
							<img src="{img filename='twitter-icon.png'}" alt="Share on Twitter">
						</a>
						<a href="http://www.facebook.com/sharer/sharer.php?u={$url|escape:"html"}/MyAccount/MyList/{$summShortId}" target="_blank" title="Share on Facebook">
							<img src="{img filename='facebook-icon.png'}" alt="Share on Facebook">
						</a>
						{include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|escape:"html"|cat:"/MyAccount/MyList/"|cat:$summShortId description="See My List '"|cat:$recordDriver->getTitle()|cat:"' at $homeLibrary"}


					</div>
				</div>

		{/if}
</div>
