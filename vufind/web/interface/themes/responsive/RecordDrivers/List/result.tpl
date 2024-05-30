{strip}
<div id="record{$summId|escape}" class="{*result *}resultsList{* TODO: what's the difference? *}">

	{if isset($summExplain)}
		<div class="hidden" id="scoreExplanationValue{$summId|escape}">{$summExplain}</div>
	{/if}

	{* Title Row *}
	<div class="row result-title-row">
		<div class="col-tn-12">
			<h2 class="h3">
				<span class="result-index">{$resultIndex}.</span>&nbsp;
				<a href="/MyAccount/MyList/{$summShortId}" class="result-title notranslate">
					{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
				</a>
				{if $summTitleStatement}
					&nbsp;-&nbsp;{$summTitleStatement|removeTrailingPunctuation|highlight|truncate:180:"..."}
				{/if}
				{if isset($summScore)}
					&nbsp;(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$summId|escape}');">{$summScore}</a>)
				{/if}
			</h2>
		</div>
	</div>

	<div class="row">
	{if $showCovers}
		<div class="coversColumn col-xs-3 col-sm-3 col-md-3 col-lg-2 text-center">
			{if $disableCoverArt != 1}
				<a href="/MyAccount/MyList/{$summShortId}" class="listResultImage">
					<img src="/bookcover.php?id={$summShortId}&type=userList&size=medium" class="listResultImage img-thumbnail" alt="{translate text='No Cover Image'}">
				</a>

				{* From Grouped Work results.tpl *}
				{*<a href="{$summUrl}">*}
					{*<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail*}{* img-responsive // shouldn't be needed *}{*" alt="{translate text='No Cover Image'}">*}
				{*</a>*}
			{/if}
		</div>
	{/if}


	<div class="{if !$showCovers}col-xs-12{else}col-xs-9 col-sm-9 col-md-9 col-lg-10{/if}">{* May turn out to be more than one situation to consider here *}

		{if $summAuthor}
			<div class="row">
				<div class="result-label col-tn-3">Created By: </div>
				<div class="result-value col-tn-9 notranslate">
					{if is_array($summAuthor)}
						{foreach from=$summAuthor item=author}
							{$author|highlight}
						{/foreach}
					{else}
						{$summAuthor|highlight}
					{/if}
				</div>
			</div>
		{/if}

		{if $summNumTitles}
			<div class="row">
				<div class="result-label col-tn-3">Number of Titles: </div>
				<div class="result-value col-tn-9 notranslate">
					{$summNumTitles} titles are in this list.
				</div>
			</div>
		{/if}

		{if $summSnippets}
			{foreach from=$summSnippets item=snippet}
				<div class="row">
					<div class="result-label col-tn-3 col-xs-3">{translate text=$snippet.caption}: </div>
					<div class="result-value col-tn-9 col-xs-9">
						{if !empty($snippet.snippet)}<span class="quotestart">&#8220;</span>...{$snippet.snippet|highlight}...<span class="quoteend">&#8221;</span><br>{/if}
					</div>
				</div>
			{/foreach}
		{/if}

		{* Description Section *}
		{if $summDescription}
			<div class="row visible-xs">
				<div class="result-label col-tn-3 col-xs-3">Description:</div>
			</div>

			<div class="row">
				<div class="result-value col-tn-12" id="descriptionValue{$summId|escape}">
					{$summDescription|highlight|truncate_html:450:"..."}
				</div>
			</div>
		{/if}


		<div class="row">
			{include file='List/result-tools.tpl' id=$summId shortId=$shortId module=$summModule summTitle=$summTitle ratingData=$summRating recordUrl=$summUrl}
		</div>
	</div>
	</div>
</div>
{/strip}