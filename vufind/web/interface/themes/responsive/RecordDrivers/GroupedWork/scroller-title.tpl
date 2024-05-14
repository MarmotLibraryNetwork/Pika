{strip}
		{* Scroller title entry plus pop-up for when the entry isn't in the catalog (eg Series carousel) *}
	{if $noResultOriginalId}
		<div id="scrollerTitle{$scrollerName}{$index}" class="scrollerTitle" onclick="return Pika.showElementInPopup('{$title|escape:quotes}', '#noResults{$index}')">
			<img src="{$bookCoverUrlMedium}" class="scrollerTitleCover" alt="{$title|escape} Cover" title="{$title|escape}">
		</div>
		<div id="noResults{$index}" style="display:none">
			<div class="row">
				<div class="result-label col-md-3">Author:</div>
				<div class="col-md-9 result-value notranslate">
					<a href='/Author/Home?author="{$author|escape:url}"'>{$author}</a>
				</div>
			</div>
			{if !empty($series)}
			<div class="series row">
				<div class="result-label col-md-3">Series:</div>
				<div class="col-md-9 result-value">
					<a href="/GroupedWork/{$noResultOriginalId}/Series">{$series}</a>
				</div>
			</div>
			{/if}
			<div class="row related-manifestation">
				<div class="col-sm-12">
					The library does not own any copies of this title.
				</div>
			</div>
		</div>
	{else}
		<button id="scrollerTitle{$scrollerName}{$index}" class="scrollerTitle" onclick="return Pika.GroupedWork.showGroupedWorkInfo('{$id}')">
			<img src="{$bookCoverUrlMedium}" class="scrollerTitleCover" alt="{$title|escape} Cover" title="{$title|escape}{if $author} by {$author|escape}{/if}">
		</button>
	{/if}
{/strip}
