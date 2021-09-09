{strip}
		{* Scroller title entry plus pop-up for when the entry isn't in the catalog (eg Series carousel) *}
	{if $noResultOriginalId}
		<div id="scrollerTitle{$scrollerName}{$index}" class="scrollerTitle" onclick="return Pika.showElementInPopup('$title', '#noResults{$index}')">
			<img src="{$bookCoverUrlMedium}" class="scrollerTitleCover" alt="{$title} Cover" title="{$title}">
		</div>
		<div id="noResults{$index}" style="display:none">
			<div class="row">
				<div class="result-label col-md-3">Author:</div>
				<div class="col-md-9 result-value notranslate">
					<a href='/Author/Home?author="{$author}"'>{$author}</a>
				</div>
			</div>
			<div class="series row">
				<div class="result-label col-md-3">Series:</div>
				<div class="col-md-9 result-value">
					<a href="/GroupedWork/{$noResultOriginalId}/Series">{$series}</a>
				</div>
			</div>
			<div class="row related-manifestation">
				<div class="col-sm-12">
					The library does not own any copies of this title.
				</div>
			</div>
		</div>
	{else}
		<div id="scrollerTitle{$scrollerName}{$index}" class="scrollerTitle" onclick="return Pika.GroupedWork.showGroupedWorkInfo('{$id}')">
			<img src="{$bookCoverUrlMedium}" class="scrollerTitleCover" alt="{$title} Cover" title="{$title}{if $author} by {$author}{/if}">
		</div>
	{/if}
{/strip}
