{strip}
	{* Covers-view tile for an Archive2 object or taxonomy term - search results, browse
	   categories and favorites alike.

	   Archive2 has its own tile rather than sharing RecordDrivers/Islandora/browse_result.tpl
	   (still used by Archive 1) because it names what it is showing. The catalog can leave a
	   cover uncaptioned, since a book jacket carries its own title; an archive thumbnail
	   rarely does, and is often the placeholder image, which tells a patron nothing at all.

	   The markup follows the related-objects tiles on the object page
	   (Archive2/sections/relatedObjectsSection.tpl), so the two read as the same kind of tile
	   and no new styling is needed. *}
	{if $browseMode == 'grid'}
		<div class="browse-list">
			<a href="{$summUrl}">
				<img class="img-responsive" src="{$bookCoverUrl}" alt=""{* Empty alt text since it just duplicates the link text*} title="{$summTitle|escape}">
				<div><strong>{$summTitle|escape}</strong></div>
			</a>
		</div>

	{else}{* Default Browse Mode (covers) *}
		{* The anchor wraps the figure rather than just the image, so the caption is part of
		   the link: it gives the link its accessible name, which is why the image alt is
		   empty rather than repeating the title. figcaption has to stay a direct child of
		   figure, so the anchor cannot move inside it. *}
		<a href="{$summUrl}">
			<figure class="browse-thumbnail-sorted">
				<img class="img-responsive" src="{$bookCoverUrlMedium}" alt="">
				<figcaption class="explore-more-category-title">
					{* 40 rather than the 60 the related-objects tiles use: a results grid is up to
					   six columns wide, so its tiles are narrower, and .browse-thumbnail-sorted
					   is a fixed 210px box that clips whatever does not fit. *}
					<strong>{$summTitle|escape|removeTrailingPunctuation|truncate:40:"..."}</strong>
				</figcaption>
			</figure>
		</a>
	{/if}
{/strip}
