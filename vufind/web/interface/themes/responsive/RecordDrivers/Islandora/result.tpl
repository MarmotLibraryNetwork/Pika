{strip}
<div id="record{if $jquerySafeId}{$jquerySafeId}{*{else}{$summId|escape}*}{/if}" class="resultsList">
	{if isset($summExplain)}
		<div class="hidden" id="scoreExplanationValue{$jquerySafeId|escape}">
			<samp style="overflow-wrap: break-word">{$summExplain}</samp>
		</div>
	{/if}

	{* Title Row *}
	<div class="row result-title-row">
		<div class="col-tn-12">
			<h2 class="h3">
			<span class="result-index">{$resultIndex}.</span>&nbsp;

			<a href="{$summUrl}" class="result-title notranslate">{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}</a>
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
	{if $showCovers}
		<div class="col-xs-12 col-sm-3{if !$viewingCombinedResults} col-md-3 col-lg-2{/if} text-center">
			{*TODO: show covers *}
			{if $disableCoverArt != 1}
				{*<div class='descriptionContent{$summShortId|escape}' style='display:none'>{$summDescription}</div>*}
				<a href="{$summUrl}">
					<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-responsive" alt="Thumbnail{if $summTitle} for '{$summTitle}'{/if}">
				</a>
			{/if}
		</div>
	{/if}

		<div class="{if !$showCovers}col-xs-12 col-sm-12{if !$viewingCombinedResults} col-md-12 col-lg-12{/if}{else}col-xs-12 col-sm-9{if !$viewingCombinedResults} col-md-9 col-lg-10{/if}{/if}col-xs-12 col-sm-9{if !$viewingCombinedResults} col-md-9 col-lg-10{/if}">

		{if $summAuthor}
			<div class="row">
				<div class="result-label col-tn-3">Author: </div>
				<div class="col-tn-9 result-value  notranslate">
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

		{if $summPublisher}
			<div class="row">
				<div class="result-label col-tn-3">Publisher: </div>
				<div class="col-tn-9 result-value">
					{$summPublisher}
				</div>
			</div>
		{/if}

		{if $summFormat}
			<div class="row">
				<div class="result-label col-tn-3">Format: </div>
				<div class="col-tn-9 result-value">
					{$summFormat}
				</div>
			</div>
		{/if}

		{if $summPubDate}
			<div class="row">
				<div class="result-label col-tn-3">Pub. Date: </div>
				<div class="col-tn-9 result-value">
					{$summPubDate|escape}
				</div>
			</div>
		{/if}

		{if $summSnippets}
			{foreach from=$summSnippets item=snippet}
				<div class="row">
					<div class="result-label col-tn-3">{translate text=$snippet.caption}: </div>
					<div class="col-tn-9 result-value">
						{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br>{/if}
					</div>
				</div>
			{/foreach}
		{/if}

		<div class="row well-small">
			<div class="col-tn-12 result-value" id="descriptionValue{$jquerySafeId|escape}">{$summDescription|highlight|html_entity_decode|truncate_html:450:"..."|strip_tags|htmlentities}</div>
		</div>

		<div class="row">
			<div class="col-tn-12">
				{include file='Archive/result-tools-horizontal.tpl'}
			</div>
		</div>

	</div>
	</div>
	{if $summCOinS}<span class="Z3988" title="{$summCOinS|escape}" style="display:none">&nbsp;</span>{/if}
</div>
{/strip}