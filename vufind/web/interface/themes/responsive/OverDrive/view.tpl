{include file="GroupedWork/load-full-record-view-enrichment.tpl"}

{strip}
	<div class="col-tn-12" xmlns="http://www.w3.org/1999/html">
		{* Search Navigation *}
		{include file="GroupedWork/search-results-navigation.tpl"}

		{* Display Title *}
		<h1 role="heading" aria-level="1" class="h2">
			{$recordDriver->getTitle()|removeTrailingPunctuation|escape}
			{if $recordDriver->getSubTitle() && $recordDriver->getSubTitle()|lower != $recordDriver->getTitle()|lower}: {$recordDriver->getSubTitle()|removeTrailingPunctuation|escape}{/if}
			{* Don't display the subtitle if it is the same text as the short title *}
			{if $recordDriver->getFormats()}
				<br><small>({implode subject=$recordDriver->getFormats() glue=", "})</small>
			{/if}
		</h1>

		<div class="row">
			<div class="col-xs-4 col-sm-5 col-md-4 col-lg-3 text-center">
				{if $disableCoverArt != 1}
					<div id="recordcover" class="text-center row">
						<img alt="{translate text='Book Cover'} for &quot;{$recordDriver->getTitle()|removeTrailingPunctuation|escape}&quot;" class="img-thumbnail" src="{$recordDriver->getBookcoverUrl('medium')}">
					</div>
				{/if}
				{if $showRatings}
					{include file="GroupedWork/title-rating-full.tpl" ratingClass="" showFavorites=0 ratingData=$recordDriver->getRatingData() showNotInterested=false hideReviewButton=true}
				{/if}
			</div>

			<div id="main-content" class="col-xs-8 col-sm-7 col-md-8 col-lg-9">

				{if $error}{* TODO: Does this get used? *}
					<div class="row">
						<div class="alert alert-danger">
							{$error}
						</div>
					</div>
				{/if}

				<div class="row">
					<div id="record-details-column" class="col-xs-12 col-sm-8">
{*					<div id="record-details-column" class="col-xs-12 col-sm-9">*}
						{include file="OverDrive/view-title-details.tpl"}
					</div>

{*					<div id="recordTools" class="col-xs-12 col-sm-6 col-md-3">*}
					<div id="recordTools" class="col-xs-12 col-sm-6 col-md-4">
						<div class="btn-toolbar">
							<div class="btn-group btn-group-vertical btn-block">
								{* Show hold/checkout button as appropriate *}
								{if $holdingsSummary.showPlaceHold}
									{* Place hold link *}
									<button class="btn btn-sm btn-block btn-primary" id="placeHold{$recordDriver->getUniqueID()|escape:"url"}" onclick="return Pika.OverDrive.placeOverDriveHold('{$recordDriver->getUniqueID()}')">{translate text="Place Hold OverDrive"}</button>
								{/if}
								{if $holdingsSummary.showCheckout}
									{* Checkout link *}
									<button class="btn btn-sm btn-block btn-primary" id="checkout{$recordDriver->getUniqueID()|escape:"url"}" onclick="return Pika.OverDrive.checkOutOverDriveTitle('{$recordDriver->getUniqueID()}')">{translate text="Check Out OverDrive"}</button>
								{/if}
							</div>
						</div>
					</div>
				</div>

				<div class="row">
					{include file='GroupedWork/result-tools-horizontal.tpl' summId=$recordDriver->getPermanentId() summShortId=$recordDriver->getPermanentId() ratingData=$recordDriver->getRatingData() recordUrl=$recordDriver->getLinkUrl() showMoreInfo=false}
				</div>

			</div>
		</div>
		<div class="row">
			{include file=$moreDetailsTemplate}
		</div>

		<span class="Z3988" title="{$recordDriver->getOpenURL()|escape}" style="display:none">&nbsp;</span>
	</div>
{/strip}