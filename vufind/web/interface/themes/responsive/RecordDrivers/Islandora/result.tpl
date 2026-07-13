{strip}
<div id="record{if $jquerySafeId}{$jquerySafeId}{*{else}{$summId|escape}*}{/if}" class="resultsList">
	{if isset($summExplain)}
		<div class="d-none" id="scoreExplanationValue{$jquerySafeId|escape}">
			<samp style="overflow-wrap: break-word">{$summExplain}</samp>
		</div>
	{/if}

	{* Title Row *}
	<div class="row result-title-row">
		<div class="col-12">
			<h2 class="h3">
			<span class="result-index">{$resultIndex}.</span>&nbsp;

			<a href="{$summUrl}" class="result-title notranslate">
				{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
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
	{if $showCovers}
		<div class="col-sm-12 col-md-3{if !$viewingCombinedResults} col-lg-3 col-xl-2{/if} text-center">
			{*TODO: show covers *}
			{if $disableCoverArt != 1}
				{*<div class='descriptionContent{$summShortId|escape}' style='display:none'>{$summDescription}</div>*}
				<a href="{$summUrl}">
					<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-fluid" alt="Thumbnail{if $summTitle} for '{$summTitle}'{/if}">
				</a>
			{/if}
		</div>
	{/if}

		<div class="{if !$showCovers}col-sm-12 col-md-12{if !$viewingCombinedResults} col-lg-12 col-xl-12{/if}{else}col-sm-12 col-md-9{if !$viewingCombinedResults} col-lg-9 col-xl-10{/if}{/if}col-sm-12 col-md-9{if !$viewingCombinedResults} col-lg-9 col-xl-10{/if}">

{* N/A
		{if $summAuthor}
			<div class="row">
				<div class="result-label col-3">Author: </div>
				<div class="col-9 result-value  notranslate">
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
				<div class="result-label col-3">Publisher: </div>
				<div class="col-9 result-value">
					{$summPublisher}
				</div>
			</div>
		{/if}
*}

		{if $summFormat || $summModel}
			<div class="row">
				{if $summFormat && $summModel && $summFormat == $summModel}
					<div class="result-label col-3">Format/Model: </div>
					<div class="col-9 result-value">{$summFormat}</div>
				{else}
					{if $summFormat}
						<div class="result-label col-3">Format: </div>
						<div class="col-9 result-value">{$summFormat}</div>
					{/if}
					{if $summModel}
						<div class="result-label col-3">Model: </div>
						<div class="col-9 result-value">{$summModel}</div>
					{/if}
				{/if}
			</div>
		{/if}

		{if $summLibrary}
			<div class="row">
				<div class="result-label col-3">Contributing Library: </div>
				<div class="col-9 result-value">
					{$summLibrary}
				</div>
			</div>
		{/if}

			{if $summPubDate}
			<div class="row">
				<div class="result-label col-3">Pub. Date: </div>
				<div class="col-9 result-value">
					{$summPubDate|escape}
				</div>
			</div>
		{/if}

		{if $summSnippets}
			{foreach from=$summSnippets item=snippet}
				<div class="row">
					<div class="result-label col-3">{translate text=$snippet.caption}: </div>
					<div class="col-9 result-value">
						{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br>{/if}
					</div>
				</div>
			{/foreach}
		{/if}

		<div class="row card card-body-small">
			<div class="col-12 result-value" id="descriptionValue{$jquerySafeId|escape}">{$summDescription|highlight|html_entity_decode|truncate_html:450:"..."|strip_tags|htmlentities}</div>
		</div>

		<div class="row">
			<div class="col-12">
				{include file='Archive2/result-tools-horizontal.tpl'}
			</div>
		</div>

	</div>
	</div>
	{if $summCOinS}<span class="Z3988" title="{$summCOinS|escape}" style="display:none">&nbsp;</span>{/if}
</div>
{/strip}