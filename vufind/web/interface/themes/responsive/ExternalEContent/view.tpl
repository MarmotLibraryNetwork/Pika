{include file="GroupedWork/load-full-record-view-enrichment.tpl"}

{strip}
	<div class="col-sm-12">
		{* Search Navigation *}
		{include file="GroupedWork/search-results-navigation.tpl"}

		{* Display Title *}
		<h1 role="heading" aria-level="1" class="h2">
					{*Short Title excludes the sub-title *}
					{$recordDriver->getShortTitle()|removeTrailingPunctuation|escape}
					{if $recordDriver->getSubTitle() && $recordDriver->getSubTitle()|lower != $recordDriver->getShortTitle()|lower}: {$recordDriver->getSubTitle()|removeTrailingPunctuation|escape}{/if}
					{* Don't display the subtitle if it is the same text as the short title *}
					{if $recordDriver->getTitleSection()}:&nbsp;{$recordDriver->getTitleSection()|removeTrailingPunctuation|escape}{/if}
					{if $recordDriver->getFormats()}
						<br><small>({implode subject=$recordDriver->getFormats() glue=", "})</small>
					{/if}
			</h1>

			<div class="row">
				<div class="col-sm-4 col-md-5 col-lg-4 col-xl-3 text-center">
					{if $disableCoverArt != 1}
						<div id="recordcover" class="text-center row">
							<img alt="{translate text='Book Cover'}" class="img-thumbnail" src="{$recordDriver->getBookcoverUrl('medium')}">
						</div>
					{/if}
					{if $showRatings}
						{include file="GroupedWork/title-rating-full.tpl" ratingClass="" showFavorites=0 ratingData=$recordDriver->getRatingData() showNotInterested=false hideReviewButton=true}
					{/if}
				</div>

				<div id="main-content" class="col-sm-8 col-md-7 col-lg-8 col-xl-9">

					<div class="row">
						<div id="record-details-column" class="col-sm-12 col-md-12 col-lg-9">
							{include file="ExternalEContent/view-title-details.tpl"}
						</div>

						<div id="recordTools" class="col-sm-12 col-md-6 col-lg-3">
							{include file="Record/result-tools.tpl"}
						</div>
					</div>

					<div class="row">
						<div class="col-sm-12">
							{include file='GroupedWork/result-tools-horizontal.tpl' summId=$recordDriver->getPermanentId() summShortId=$recordDriver->getPermanentId() ratingData=$recordDriver->getRatingData() recordUrl=$recordDriver->getLinkUrl() showMoreInfo=false}
						</div>
					</div>

				</div>
			</div>

			<div class="row">
				{include file=$moreDetailsTemplate}
			</div>

			<span class="Z3988" title="{$recordDriver->getOpenURL()|escape}" style="display:none">&nbsp;</span>
	</div>
{/strip}