{strip}
	<div class="alert alert-info">NoveList provides detailed suggestions for titles you might like if you enjoyed this book.  Suggestions are based on recommendations from librarians and other contributors.</div>
	<div id="similarTitlesNovelist" class="striped div-striped">
		{foreach from=$similarTitles item=similarTitle name="recordLoop"}
			<div class="novelist-similar-item">
				<div class="novelist-similar-item-header notranslate">{if $similarTitle.fullRecordLink}<a href='{$similarTitle.fullRecordLink}'>{/if}{$similarTitle.title|removeTrailingPunctuation}{if $similarTitle.fullRecordLink}</a>{/if}
					&nbsp;by <a href="/Search/Results?lookfor={$similarTitle.author|escape:url}" class="notranslate">{$similarTitle.author}</a></div>
				<div class="novelist-similar-item-reason">
					{$similarTitle.reason}
				</div>
			</div>
		{/foreach}
	</div>
{/strip}