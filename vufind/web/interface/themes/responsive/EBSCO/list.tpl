<div id="searchInfo">
	{* Recommendations *}
	{if $topRecommendations}
		{foreach from=$topRecommendations item="recommendations"}
			{include file=$recommendations}
		{/foreach}
	{/if}

	{* Listing Options *}
	<div class="resulthead">
		{if $recordCount}
			{translate text="Showing"}
			<b>{$recordStart}</b> - <b>{$recordEnd}</b>
			{translate text='of'} <b>{$recordCount}</b>
			{if $searchType == 'basic'}{translate text='for search'}: <b>'{$lookfor|escape:"html"}'</b>,{/if}
		{/if}
		<span class="hidden-phone">
			,&nbsp;{translate text='query time'}: {$qtime}s
		</span>

		<div class="clearer"></div>
	</div>
	{* End Listing Options *}

	{if $subpage}
		{include file=$subpage}
	{else}
		{$pageContent}
	{/if}

	{if $pageLinks.all}<div class="pagination">{$pageLinks.all}</div>{/if}

	{if $showSearchTools}
		<div class="searchtools well small">
			<strong>{translate text='Search Tools'}:</strong>
			&nbsp;&nbsp;<a href="{$rssLink|escape}"><span class="glyphicon glyphicon-inbox" aria-hidden="true"></span>&nbsp;{translate text='Get RSS Feed'}</a>
			&nbsp;&nbsp;<a href="#" onclick="return VuFind.Account.ajaxLightbox('/Search/AJAX?method=getEmailForm', true); "><span class="glyphicon glyphicon-envelope" aria-hidden="true"></span>&nbsp;{translate text='Email this Search'}</a>
			{if $savedSearch}
				&nbsp;&nbsp;<a href="#" onclick="return VuFind.Account.saveSearch('{$searchId}')"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span>&nbsp;{translate text='save_search_remove'}</a>
			{else}
				&nbsp;&nbsp;<a href="#" onclick="return VuFind.Account.saveSearch('{$searchId}')"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>&nbsp;{translate text='save_search'}</a>
			{/if}
			&nbsp;&nbsp;<a href="{$excelLink|escape}"><span class="glyphicon glyphicon-th" aria-hidden="true"></span>&nbsp;{translate text='Export To Excel'}</a>
		</div>
	{/if}
</div>
