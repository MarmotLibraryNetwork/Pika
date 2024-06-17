<div class="col-xs-12">
{if $loggedIn}

	{include file="MyAccount/patronWebNotes.tpl"}

	{* Alternate Mobile MyAccount Menu *}
	{include file="MyAccount/mobilePageHeader.tpl"}

	<span class='availableHoldsNoticePlaceHolder'></span>

	<h1 role="heading" aria-level="1" class="h2">{translate text='My Reading History'} {if $historyActive == true}<small><a id="readingListWhatsThis" href="#" onclick="$('#readingListDisclaimer').toggle();return false;">(What's This?)</a></small>{/if}</h1>

		{include file="MyAccount/switch-linked-user-form.tpl" label="View Reading History for" actionPath="/MyAccount/ReadingHistory"}

	<br>
		{if $offline}
			<div class="alert alert-warning"><strong>The library system is currently offline.</strong> We are unable to retrieve information about your reading history at this time.</div>
		{else}
			{strip}

		{if $masqueradeMode && !$allowReadingHistoryDisplayInMasqueradeMode}
			<div class="row">
				<div class="alert alert-warning">
					Display of the patron's reading history is disabled in Masquerade Mode.
				</div>
			</div>
		{/if}

	<div class="row">
		<div id="readingListDisclaimer" {if $historyActive == true}style="display: none"{/if} class="alert alert-info">
			{* some necessary white space in notice was previously stripped out when needed. *}
		{/strip}
			{translate text="ReadingHistoryNotice"}
		{strip}
		</div>
	</div>
			<div class="row">
				<div class="alert alert-info text-center"><a href="https://marmot-support.atlassian.net/l/cp/GPkTPPrd" target="_blank">My Reading History Guide  (opens in new tab)</a></div>
			</div>

	{if !$masqueradeMode || ($masqueradeMode && $allowReadingHistoryDisplayInMasqueradeMode)}

			{if $user->trackReadingHistory && !$user->initialReadingHistoryLoaded}
					{*Notice for users that have opted in to reading history *}
					<div class="alert alert-warning">
						<p>Please note that your reading history has not been completely processed yet. The titles displayed below may initially be incomplete or take a long time to load. The process generally takes overnight for your reading history to display in full once you have opted in to have your reading history recorded.
						</p>
					</div>
			{/if}


		{* Do not display Reading History in Masquerade Mode, unless the library has allowed it *}
	<form id="readingListForm" class="form-inline readingHistoryActionForm">
		{* Reading History Actions *}
		<div class="row">
			<input type="hidden" name="page" value="{$page}">
			<input type="hidden" name="patronId" value="{$selectedUser}">
			<input type="hidden" name="readingHistoryAction" id="readingHistoryAction" value="">

			<div id="readingListActionsTop" class="col-xs-6">
				<div class="btn-group btn-group-sm">
					{if $historyActive == true}
						<button class="btn btn-sm btn-info" onclick="return Pika.Account.ReadingHistory.exportListAction()">Export To Excel</button>
						{if $transList}
							<button class="btn btn-sm btn-warning" onclick="return Pika.Account.ReadingHistory.deletedMarkedAction()">Delete Marked</button>
						{/if}
					{else}
						<button class="btn btn-sm btn-primary" onclick="return Pika.Account.ReadingHistory.optInAction()">Start Recording My Reading History</button>
					{/if}
				</div>
			</div>
			{if $historyActive == true}
				<div class="col-xs-6">
					<div class="btn-group btn-group-sm pull-right">
				{if $transList}
					<button class="btn btn-sm btn-danger " onclick="return Pika.Account.ReadingHistory.deleteAllAction()">Delete All</button>
				{/if}
				<button class="btn btn-sm btn-danger" onclick="return Pika.Account.ReadingHistory.optOutAction()">Stop Recording My Reading History</button>
				</div>
			</div>
			{/if}
		</div>
	</form>
	<div class="row">
	

			{if $transList || $isReadingHistorySearch}
					<hr>
				{* Reading history search *}
				<div class="row readingHistorySearch">
				<div class="col-xs-3">
					<label for="searchTerm">Search Reading History</label>
				</div>
				<div class="col-xs-6">
					<input type="search" name="searchTerm" id="searchTerm" class="form-control"  onkeydown="return event.key != 'Enter';" value="{if $searchTerm}{$searchTerm|escape}{*Escape to prevent javascript injection*}{/if}">
				</div>
				<div class="col-xs-3">
					<select name="searchBy" id="searchBy" class="form-control" aria-label="Search by">
						<option value="title" {if $searchBy == 'title'}selected{/if}>by Title</option>
						<option value="author" {if $searchBy == 'author'}selected{/if}>by Author</option>
					</select>
					<button class="btn btn-default" type="submit" onclick="return Pika.Account.ReadingHistory.searchReadingHistoryAction()">Search</button>
				</div>
				</div>
				<hr>
				{* Results Page Options *}
				<div id="pager" class="col-xs-12">
					<div class="row">
						<div class="form-group col-sm-5" id="recordsPerPage">
							<label for="pagesize" class="control-label">Records Per Page&nbsp;</label>
							<select id="pagesize" class="pagesize form-control{* input-sm*}">
								<option value="20"{if $recordsPerPage == 20} selected="selected"{/if}>20</option>
								<option value="40"{if $recordsPerPage == 40} selected="selected"{/if}>40</option>
								<option value="60"{if $recordsPerPage == 60} selected="selected"{/if}>60</option>
								<option value="80"{if $recordsPerPage == 80} selected="selected"{/if}>80</option>
								<option value="100"{if $recordsPerPage == 100} selected="selected"{/if}>100</option>
							</select>
						</div>
						<div class="form-group col-sm-5" id="sortOptions">
							<label for="sortMethod" class="control-label">Sort By&nbsp;</label>
							<select class="sortMethod form-control" id="sortMethod" name="accountSort">
								{foreach from=$sortOptions item=sortOptionLabel key=sortOption}
									<option value="{$sortOption}" {if $sortOption == $defaultSortOption}selected="selected"{/if}>{$sortOptionLabel}</option>
								{/foreach}
							</select>
						</div>
						<div class="form-group col-sm-2" id="coverOptions">
							<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
						</div>
					</div>
				</div>

				{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}

				{* Header Row with Column Labels *}
				<div class="row hidden-xs">
					<div class="col-sm-1">
						<input id="selectAll" type="checkbox" onclick="Pika.toggleCheckboxes('.titleSelect', '#selectAll');" title="Select All/Deselect All" aria-label="Select All/Deselect All">
					</div>
					{if $showCovers}
					<div class="col-sm-2">
						<strong>{translate text='Cover'}</strong>
					</div>
					{/if}
					<div class="{if $showCovers}col-sm-7{else}col-sm-9{/if}">
						<strong>{translate text='Title'}</strong>
					</div>
					<div class="col-sm-2">
						<strong>{translate text='Last Read Around'}</strong>
					</div>
				</div>
          {if $historyActive == true && $isReadingHistorySearch == true && count($transList) lt 1}
              {* History search request, No entries in the history *}
						<div class="alert alert-warning text-center">There are no entries in your reading history that match your search <strong>{if $searchTerm}{$searchTerm|escape}{* Escape term to prevent embeded javascript in term from executing *}{/if}</strong></div>
          {/if}
				{* Reading History Entries *}
				<div class="striped">

					{foreach from=$transList item=record name="recordLoop" key=recordKey}
						<div class="row">
							<div class="col-tn-12">

						<div class="row result-title-row">
							<div class="col-xs-12">
								<h2 class="h3">
									{if $record.recordId && $record.linkUrl}
										<a href="{$record.linkUrl}" class="title">{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}</a>
									{else}
										{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation}{/if}
									{/if}
									{if $record.title2}
										<div class="searchResultSectionInfo">
											{$record.title2|removeTrailingPunctuation|truncate:180:"..."|highlight}
										</div>
									{/if}
								</h2>
							</div>
						</div>

						<div class="row">

							{* Cover Column *}
							{if $showCovers}
							<div class="col-tn-3">
								<div class="row">
									<div class="col-xs-12 col-sm-1">
										<input type="checkbox" name="selected[{$record.permanentId|escape:"url"}]" class="titleSelect" value="rsh{$record.itemindex}" id="rsh{$record.itemindex}" aria-label="Select title to delete">
									</div>
									<div class="col-xs-12 col-sm-10">
										{if $record.coverUrl}
											{if $record.recordId && $record.linkUrl}
												<a href="{$record.linkUrl}" id="descriptionTrigger{$record.recordId|escape:"url"}">
													<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{if !$record.title}Cover image for reading history item.{else}Cover image for {$record.title}.{/if}">
												</a>
											{else} {* Cover Image but no Record-View link *}
												<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{if !$record.title}Cover image for reading history item.{else}Cover image for {$record.title}.{/if}">
											{/if}
										{/if}
									</div>
								</div>
							</div>
							{else}
								<div class="col-tn-1">
									<input type="checkbox" name="selected[{$record.permanentId|escape:"url"}]" class="titleSelect" value="rsh{$record.itemindex}" id="rsh{$record.itemindex}" aria-label="Select title to delete">
								</div>
							{/if}

							{* Title Details Column *}
							<div class="{if $showCovers}col-tn-7 col-sm-7{else}col-tn-9 col-sm-9{/if}">

								{if $record.author}
									<div class="row">
										<div class="result-label col-tn-3">{translate text='Author'}</div>
										<div class="result-value col-tn-9">
											{if is_array($record.author)}
												{foreach from=$summAuthor item=author}
													<a href='/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
												{/foreach}
											{else}
												<a href='/Author/Home?author="{$record.author|escape:"url"}"'>{$record.author|highlight}</a>
											{/if}
										</div>
									</div>
								{/if}

								{if $record.publicationDate}
									<div class="row">
										<div class="result-label col-tn-3">{translate text='Published'}</div>
										<div class="result-value col-tn-9">
											{$record.publicationDate|escape}
										</div>
									</div>
								{/if}

								<div class="row">
									<div class="result-label col-tn-3">{translate text='Format'}</div>
									<div class="result-value col-tn-9">
										{if is_array($record.format)}
											{implode subject=$record.format glue=", "}
										{else}
											{$record.format}
										{/if}
									</div>
								</div>

								{if $showRatings == 1}
										{* $showRatings is set by UInterface method loadDisplayOptions() *}
									{if $record.recordId != -1 && $record.ratingData}
										<div class="row">
											<div class="result-label col-tn-3">Rating&nbsp;</div>
											<div class="result-value col-tn-9">
												{include file="GroupedWork/title-rating.tpl" ratingClass="" id=$record.permanentId ratingData=$record.ratingData showNotInterested=false}
											</div>
										</div>
									{/if}
								{/if}
							</div>

							{* Last Read Date Column *}
							<div class="col-tn-12 {if $showCovers}col-tn-offset-3{else}col-tn-offset-1{/if} col-sm-2 col-sm-offset-0">
								<div class="row">
								{* on xs viewports, the offset lines up the date with the title details *}
								{if is_numeric($record.checkout)}
									{$record.checkout|date_format}
								{else}
									{$record.checkout|escape}
								{/if}
								{if $record.lastCheckout} to {$record.lastCheckout|escape}{/if}
								{* Do not show checkin date since historical data from initial import is not correct.
								{if $record.checkin} to {$record.checkin|date_format}{/if}
								*}
								</div>
								{if $record.permanentId}
								<div class="row">
									<div class="col-tn-12 result-tools-horizontal{*to get the brown gradient to apply to button*}">
										<button onclick="return Pika.GroupedWork.showSaveToListForm(this, '{$record.permanentId|escape:"url"}');" class="btn btn-small">Add To List</button>
									</div>
								</div>
                {/if}
							</div>
						</div>

							</div>
						</div>
					{/foreach}
				</div>

				<hr>

				<div class="row">
					<div class="col-xs-12">
					<div id="readingListActionsBottom" class="btn-group btn-group-sm">
							{if $historyActive == true}
								<button class="btn btn-sm btn-info" onclick="return Pika.Account.ReadingHistory.exportListAction()">Export To Excel</button> 
								{if $transList}
									<button class="btn btn-sm btn-warning" onclick="return Pika.Account.ReadingHistory.deletedMarkedAction()">Delete Marked</button>
								{/if}
							{else}
								<button class="btn btn-sm btn-primary" onclick="return Pika.Account.ReadingHistory.optInAction()">Start Recording My Reading History</button>
							{/if}
					</div>
				</div>
				</div>

				{if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
			{elseif $historyActive == true && $isReadingHistorySearch == false}
				{* No entries in the history, but the history is active *}
				<div class="alert alert-info">There are no entries in your reading history.</div>
			{/if}

		</div>
		</form>
	{/if}

	{/strip}
			{/if}
{else}
		{* This should never get displayed. Users should automatically be redirected to login page*}
    {include file="MyAccount/loginRequired.tpl"}
{/if}
</div>

<script>
	{literal}
	// Setup sorting for logs
	document.addEventListener('DOMContentLoaded', function() {
		var pagesizeSelectElement = document.getElementById('pagesize');
		// Add event listener for click to sort options
		pagesizeSelectElement.addEventListener('click', function(e) {
			let val = checkSelectedOption(this);
			if(val !== null) {
				//alert("Selected Value: " + val)
				Pika.changePageSize()
			}
		})

		// Add event listener for keypress (accessibility)
		pagesizeSelectElement.addEventListener('keypress', function(e) {
			let val = checkSelectedOption(this);
			if(e.key === 'Enter' && val !== null) {
				Pika.changePageSize()
			}
		})

		var sortMethodElement = document.getElementById('sortMethod');
		// Add event listener for click to sort options
		sortMethodElement.addEventListener('click', function(e) {
			let val = checkSelectedOption(this);
			if(val !== null) {
				//alert("Selected Value: " + val)
				Pika.Account.changeAccountSort(val)
			}
		})

		// Add event listener for keypress (accessibility)
		sortMethodElement.addEventListener('keypress', function(e) {
			let val = checkSelectedOption(this);
			if(e.key === 'Enter' && val !== null) {
				Pika.Account.changeAccountSort(val)
			}
		})
	});
	{/literal}
</script>