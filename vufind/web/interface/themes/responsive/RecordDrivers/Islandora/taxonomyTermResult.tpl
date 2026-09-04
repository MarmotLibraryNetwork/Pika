{strip}
{* Search result for an Islandora 2 taxonomy term.
   Terms have no format, model, or contributing library, so this shows the thumbnail, the
   name, the vocabulary the term belongs to, and the description. The title, the thumbnail
   and the More Info button all link to the typed Archive2 term page for that vocabulary
   (/Archive2/Person, /Archive2/Place, and so on). *}
<div id="record{$jquerySafeId}" class="resultsList">
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

				{if isset($summScore)}
					&nbsp;<small>(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$jquerySafeId|escape}');">{$summScore}</a>)</small>
				{/if}
			</h2>
		</div>
	</div>

	<div class="row">
	{if $showCovers}
		{* The driver leaves $bookCoverUrlMedium empty when covers are switched off for this
		   patron, so that it can skip the API call the thumbnail would otherwise cost. *}
		<div class="col-sm-12 col-md-3{if !$viewingCombinedResults} col-lg-3 col-xl-2{/if} text-center">
			{if $bookCoverUrlMedium}
				<a href="{$summUrl}">
					<img src="{$bookCoverUrlMedium}" class="listResultImage img-thumbnail img-fluid" alt="Thumbnail{if $summTitle} for '{$summTitle}'{/if}">
				</a>
			{/if}
		</div>
	{/if}

		<div class="{if !$showCovers}col-sm-12 col-md-12{if !$viewingCombinedResults} col-lg-12 col-xl-12{/if}{else}col-sm-12 col-md-9{if !$viewingCombinedResults} col-lg-9 col-xl-10{/if}{/if}">

			{if $summVocabularyLabel}
				<div class="row">
					<div class="result-label col-3">Taxonomy: </div>
					<div class="col-9 result-value">{$summVocabularyLabel}</div>
				</div>
			{/if}

			{if $summDescription}
				<div class="row">
					<div class="col-12 result-value" id="descriptionValue{$jquerySafeId|escape}">{$summDescription|highlight|html_entity_decode|truncate_html:450:"..."|strip_tags|htmlentities}</div>
				</div>
			{/if}

			<div class="row">
				<div class="col-12">
					{* Terms can be saved to a list since D-5469; they are stored as tax_{vocabulary}:{tid}.
					   showFavorites is left to the page-level variable, exactly as the object result does. *}
					{include file='Archive2/result-tools-horizontal.tpl'}
				</div>
			</div>
		</div>
	</div>
</div>
{/strip}