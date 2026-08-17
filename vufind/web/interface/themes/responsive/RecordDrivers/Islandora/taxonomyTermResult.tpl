{strip}
{* Search result for an Islandora 2 taxonomy term.
   Terms have no cover, format, model, or contributing library, and no landing page to
   link to yet, so this shows the name and description only. *}
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

				{*TODO: no term landing page yet; make this an anchor once one exists *}
				<span class="result-title notranslate">
					{if !$summTitle|removeTrailingPunctuation}{translate text='Title not available'}{else}{$summTitle|removeTrailingPunctuation|highlight|truncate:180:"..."}{/if}
				</span>

				{if isset($summScore)}
					&nbsp;<small>(<a href="#" onclick="return Pika.showElementInPopup('Score Explanation', '#scoreExplanationValue{$jquerySafeId|escape}');">{$summScore}</a>)</small>
				{/if}
			</h2>
		</div>
	</div>

	{if $summDescription}
		<div class="row">
			<div class="col-tn-12">
				<div class="row well-small">
					<div class="col-tn-12 result-value" id="descriptionValue{$jquerySafeId|escape}">{$summDescription|highlight|html_entity_decode|truncate_html:450:"..."|strip_tags|htmlentities}</div>
				</div>
			</div>
		</div>
	{/if}
</div>
{/strip}