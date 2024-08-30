{strip}
	<div id="groupedRecord{$summId|escape}" class="resultsList">
		<a id="record{$summId|escape}"></a>
		{if isset($summExplain)}
			<div class="hidden" id="scoreExplanationValue{$summId|escape}">
					<samp  style="overflow-wrap: break-word">{$summExplain}</samp>
			</div>
		{/if}

		{* Title Row *}
		<div class="row result-title-row">
			<div class="col-tn-12">
				<h2 class="h3">
					<span class="result-index">{$resultIndex}.</span>&nbsp;
					<a href="{$summUrl}" class="result-title notranslate">
						{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
						{if $summSubTitle|removeTrailingPunctuation}: {$summSubTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
					</a>
					{if $summTitleStatement}
						&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|highlight|truncate:180:"..."}
					{/if}

					{if isset($summScore)}
						&nbsp;<small>(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$summId|escape}');">{$summScore}</a>)</small>
					{/if}

				</h2>
			</div>
		</div>

		<div class="row">
				{if $showBookshelf}
					<div class="col-tn-1 col-sm-1" aria-live="polite">
						<input type="checkbox" id="select_{$summId|escape}" class="checkbox checkbox-results" aria-label="Add title to bookshelf" title="Add title to bookshelf">
					</div>
				{/if}
			{if $showCovers}

				<div class="coversColumn col-xs-3 col-sm-3{if !$viewingCombinedResults} col-md-3 col-lg-2{/if} text-center">

					{if $disableCoverArt != 1}
						<a href="{$summUrl}">
							<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail" alt="Book cover{if $summTitle} for &quot;{$summTitle|escape}&quot;.{/if}">
						</a>
					{/if}

					{if $showRatings}
						<div class="title-rating list-rating" {*preserve trailing space for good parsing *}
						     data-user_rating="{$summRating.user}" {*preserve trailing space for good parsing *}
						     data-rating_title="{$summTitle}" {*preserve trailing space for good parsing *}
						     data-id="{$summId}" {*preserve trailing space for good parsing *}
						     data-show_review="{if $showComments  && (!$loggedIn || !$user->noPromptForUserReviews)}1{else}0{/if}"
						>
							{if $summRating.user}
							<div class="text-left small">Your rating: {$summRating.user} stars</div>
							{else}
							<div class="text-left small">Rate this title:</div>	 
							{/if}
							{include file='MyAccount/star-rating.tpl' id=$summId ratingData=$summRating ratingTitle=$summTitle}
						</div>
						{if $showNotInterested == true}
							<button class="button notInterested" title="Select Not Interested if you don't want to see this title again." onclick="return Pika.GroupedWork.markNotInterested('{$summId}');">Not&nbsp;Interested</button>
						{/if}
						{* include file="GroupedWork/title-rating.tpl" ratingClass="" id=$summId ratingData=$summRating *}
					{/if}
				</div>
			{/if}

			{if $showBookshelf}
				<div class="{if !$showCovers}col-xs-11{else}col-xs-8 col-sm-8{if !$viewingCombinedResults} col-md-8 col-lg-9{/if}{/if}">{* May turn out to be more than one situation to consider here *}
			{else}
				<div class="{if !$showCovers}col-tn-12{else}col-tn-9 col-sm-9{if !$viewingCombinedResults} col-md-9 col-lg-10{/if}{/if}">{* May turn out to be more than one situation to consider here *}
			{/if}

				{if $summAuthor}
					<div class="row">
						<div class="result-label col-tn-3">Author: </div>
						<div class="result-value col-tn-8 notranslate">
							{if is_array($summAuthor)}
								{foreach from=$summAuthor item=author}
									<a href='/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
								{/foreach}
							{else}
								<a href='/Author/Home?author="{$summAuthor|escape:"url"}"'>{$summAuthor|highlight}</a>
							{/if}
						</div>
					</div>
				{/if}

				{if $seriesVolume}
					{* For Grouped Work Series Page *}
					<div class="row">
						<div class="result-label col-tn-3">{translate text='Series Volume'}:</div>
						<div class="col-tn-9 result-value">{$seriesVolume}</div>
					</div>
				{/if}

				{if $showSeries}
					{if $summSeries}
						<div class="series novelistSeries{$summISBN} row">
							<div class="result-label col-tn-3">{translate text='NoveList Series'}:</div>
							<div class="result-value col-tn-8">
								<a href="/GroupedWork/{$summId}/Series">{$summSeries.seriesTitle}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}<br>
							</div>
						</div>
					{/if}

					{assign var=indexedSeries value=$recordDriver->getIndexedSeries()}
					{if $indexedSeries}
						<div class="series series{$summISBN} row">
							<div class="result-label col-tn-3">Series: </div>
							<div class="result-value col-tn-8">
								{assign var=showMoreSeries value=false}
								{if count($indexedSeries) > 4}
									{assign var=showMoreSeries value=true}
								{/if}
								{foreach from=$indexedSeries item=seriesItem name=loop}
										<a href="/Search/Results?basicType=Series&lookfor=%22{$seriesItem.seriesTitle|escape:"url"}%22">{$seriesItem.seriesTitle|escape}</a>{if $seriesItem.volume} volume {$seriesItem.volume}{/if}<br>
										{if $showMoreSeries && $smarty.foreach.loop.iteration == 3}
											<a onclick="$('#moreSeries_{$summId}').show();$('#moreSeriesLink_{$summId}').hide();" id="moreSeriesLink_{$summId}">More Series...</a>
											<div id="moreSeries_{$summId}" style="display:none">
										{/if}

								{/foreach}
								{if $showMoreSeries}
									</div>
								{/if}
							</div>
						</div>
					{/if}

				{/if}

				{if $showPublisher}
				{if $alwaysShowSearchResultsMainDetails || $summPublisher}
					<div class="row">
						<div class="result-label col-tn-3">Publisher: </div>
						<div class="result-value col-tn-8">
							{if $summPublisher}
								{$summPublisher}
							{elseif $alwaysShowSearchResultsMainDetails}
								{translate text="Not Supplied"}
							{/if}
						</div>
					</div>
				{/if}
				{/if}

				{if $showPublicationDate}
					{if $alwaysShowSearchResultsMainDetails || $summPubDate}
						<div class="row">
							<div class="result-label col-tn-3">Pub. Date: </div>
							<div class="result-value col-tn-8">
								{if $summPubDate}
									{$summPubDate|escape}
								{elseif $alwaysShowSearchResultsMainDetails}
									{translate text="Not Supplied"}
								{/if}
							</div>
						</div>
					{/if}
				{/if}

				{if $showEditions}
					{if $alwaysShowSearchResultsMainDetails || $summEdition}
						<div class="row">
							<div class="result-label col-tn-3">Edition: </div>
							<div class="result-value col-tn-8">
								{if $summEdition}
									{$summEdition}
								{elseif $alwaysShowSearchResultsMainDetails}
									{translate text="Not Supplied"}
								{/if}
							</div>
						</div>
					{/if}
				{/if}

				{if $showArInfo && $summArInfo}
					<div class="row">
						<div class="result-label col-tn-3">{translate text='Accelerated Reader'}: </div>
						<div class="result-value col-tn-8">
							{$summArInfo}
						</div>
					</div>
				{/if}

				{if $showLexileInfo && $summLexileInfo}
					<div class="row">
						<div class="result-label col-tn-3">{translate text='Lexile measure'}: </div>
						<div class="result-value col-tn-8">
							{$summLexileInfo}
						</div>
					</div>
				{/if}

				{if $showFountasPinnell && $summFountasPinnell}
					<div class="row">
						<div class="result-label col-tn-3">{translate text='Fountas &amp; Pinnell'}: </div>
						<div class="result-value col-tn-8">
							{$summFountasPinnell}
						</div>
					</div>
				{/if}

				{if $showPhysicalDescriptions}
					{if $alwaysShowSearchResultsMainDetails || $summPhysicalDesc}
						<div class="row">
							<div class="result-label col-tn-3">{translate text='Physical Desc'}: </div>
							<div class="result-value col-tn-8">

								{if $summPhysicalDesc}
									{$summPhysicalDesc|escape}
								{elseif $alwaysShowSearchResultsMainDetails}
									{translate text="Not Supplied"}
								{/if}
							</div>
						</div>
					{/if}
				{/if}

				{if $showLanguages && $summLanguage}
					<div class="row">
						<div class="result-label col-tn-3">Language: </div>
						<div class="result-value col-tn-8">
							{if is_array($summLanguage)}
								{', '|implode:$summLanguage}
							{else}
								{$summLanguage}
							{/if}
						</div>
					</div>
				{/if}

				{if $showRatings && $summRating.average}
					<div class="row">
						<div class="result-label col-tn-3">Average Rating: </div>
						<div class="result-value col-tn-8">
							{math equation="round(average_rating,1)" average_rating=$summRating.average} stars
						</div>
					</div>
				{/if}

				{if $summSnippets}
					{foreach from=$summSnippets item=snippet}
						<div class="row">
							<div class="result-label col-tn-3">{translate text=$snippet.caption}: </div>
							<div class="result-value col-tn-8">
								{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br>{/if}
							</div>
						</div>
					{/foreach}
				{/if}


				{* Short Mobile Entry for Formats when there aren't hidden formats *}
{*				{if !empty($relatedManifestations)}*}
				<div class="row visible-xs">

					{* Determine if there were hidden Formats for this entry *}
					{assign var=hasHiddenFormats value=false}
					{foreach from=$relatedManifestations item=relatedManifestation}
					{if $relatedManifestation.hideByDefault}
						{assign var=hasHiddenFormats value=true}
					{/if}
					{/foreach}

					{* If there weren't hidden formats, show this short Entry (mobile view only). The exception is single format manifestations, they
					   won't have any hidden formats and will be displayed *}
					{if !$hasHiddenFormats && count($relatedManifestations) != 1}
						<div class="hidethisdiv{$summId|escape} result-label col-tn-3">
							Formats:
						</div>
						<div class="hidethisdiv{$summId|escape} result-value col-tn-8">
							<a href="#" onclick="$('#relatedManifestationsValue{$summId|escape},.hidethisdiv{$summId|escape}').toggleClass('hidden-xs');return false;">
									{implode subject=$relatedManifestations|@array_keys glue=",&nbsp;"}
							</a>
						</div>
					{/if}

				</div>
{*				{/if}*}

				{* Formats Section *}
				<div class="row">
					<div class="{if !$hasHiddenFormats && count($relatedManifestations) != 1}hidden-xs {/if}col-sm-12" id="relatedManifestationsValue{$summId|escape}">
						{* Hide Formats section on mobile view, unless there is a single format or a format has been selected by the user *}
						{* relatedManifestationsValue ID is used by the Formats button *}

						{include file="GroupedWork/relatedManifestations.tpl" id=$summId}

					</div>
				</div>

				{if !$viewingCombinedResults}
					{* Description Section *}
					{if $summDescription}
						<div class="row visible-xs">
							<div class="result-label col-tn-3">Description:</div>
						</div>

						<div class="row">
							<div class="result-value col-tn-12" id="descriptionValue{$summId|escape}">
								{$summDescription|highlight|truncate_html:450:"..."}
							</div>
						</div>
					{/if}

					<div class="row">
						<div class="col-xs-12">
							{include file='GroupedWork/result-tools-horizontal.tpl' id=$summId shortId=$shortId ratingData=$summRating recordUrl=$summUrl}
							{* TODO: id & shortId shouldn't be needed to be specified here, otherwise need to note when used.
								summTitle only used by cart div, which is disabled as of now. 12-28-2015 plb *}
						</div>
					</div>
				{/if}

			</div>

		</div>


		{if $summCOinS}<span class="Z3988" title="{$summCOinS|escape}" style="display:none">&nbsp;</span>{/if}
	</div>
{/strip}
