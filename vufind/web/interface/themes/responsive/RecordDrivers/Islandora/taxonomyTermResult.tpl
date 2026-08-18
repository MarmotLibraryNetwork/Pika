{strip}
{* Search result for an Islandora 2 taxonomy term.
   Terms have no cover, format, model, or contributing library, so this shows the name, the
   vocabulary the term belongs to, and the description only. The title and the More Info
   button both link to the typed Archive2 term page for that vocabulary (/Archive2/Person,
   /Archive2/Place, and so on). *}
<div id="record{$jquerySafeId}" class="resultsList">
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

				<a href="{$summUrl}" class="result-title notranslate">
					{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
				</a>

				{if isset($summScore)}
					&nbsp;<small>(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$jquerySafeId|escape}');">{$summScore}</a>)</small>
				{/if}
			</h2>
		</div>
	</div>

	{if $summVocabularyLabel}
		<div class="row">
			<div class="result-label col-tn-3">Taxonomy: </div>
			<div class="col-tn-9 result-value">{$summVocabularyLabel}</div>
		</div>
	{/if}

	{if $summDescription}
		<div class="row">
			<div class="col-tn-12">
				<div class="row well-small">
					<div class="col-tn-12 result-value" id="descriptionValue{$jquerySafeId|escape}">{$summDescription|highlight|html_entity_decode|truncate_html:450:"..."|strip_tags|htmlentities}</div>
				</div>
			</div>
		</div>
	{/if}

	<div class="row">
		<div class="col-tn-12">
			{* showFavorites=0: the Add to favorites button saves a node id, and a term is not a node. *}
			{include file='Archive2/result-tools-horizontal.tpl' showFavorites=0}
		</div>
	</div>
</div>
{/strip}