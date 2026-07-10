{* TODO: possibly obsolete
Only used by un-used method SearchObject_Solr->getSuggestionListHTML() *}
{strip}
	<a id="record{$summId|escape:"url"}"></a>
	<div id="groupedRecord{$summId|escape}" class="resultsList row">
		<div class="col-sm-12 col-md-3 col-lg-3 col-xl-2 text-center">
			<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-responsive" alt="Book cover for &quot;{$summTitle}&quot;.">
			{* TODO: if ever used, update with keyboard-accessible star rating interface
			{include file="GroupedWork/title-rating.tpl" ratingClass="" id=$summId ratingData=$summRating showNotInterested=true}*}
		</div>
		<div class="col-sm-12 col-md-9 col-lg-9 col-xl-10">
			<div class="row">
				<div class="col-sm-12">
					<a href="{$summUrl}" class="result-title notranslate">{$summTitle|removeTrailingPunctuation|escape}</a><br>
					{if $summTitleStatement}
						&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|truncate:180:"..."|highlight}
					{/if}
				</div>
			</div>

			{if $summAuthor}
				<div class="row">
					<div class="result-label col-lg-3">Author: </div>
					<div class="col-lg-9 result-value  notranslate">
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

			{if $summSeries}
				<div class="series{$summISBN} row">
					<div class="result-label col-lg-3">Series: </div>
					<div class="col-lg-9 result-value">
						<a href="/GroupedWork/{$summId}/Series">{$summSeries.seriesTitle}</a>{if $summSeries.volume} volume {$summSeries.volume}{/if}
					</div>
				</div>
			{/if}

			<div class="row well-small">
				<div class="col-lg-12 result-value" id="descriptionValue{$summId|escape}">{$summDescription|truncate_html:450:"..."}</div>
			</div>

			<div class="row">
				<div class="col-sm-12">
					{include file='GroupedWork/relatedManifestations.tpl'}
				</div>
			</div>

			<div class="row">
				<div class="col-sm-12">
					{include file='GroupedWork/result-tools-horizontal.tpl' id=$summId shortId=$shortId summTitle=$summTitle ratingData=$summRating recordUrl=$summUrl}
				</div>
			</div>
		</div>

	</div>
{/strip}