{strip}
	{if $related_place}
		<div class="related-objects results-covers home-page-browse-thumbnails">
			{foreach from=$related_place item=place}
				<figure class="browse-thumbnail-sorted">
					<a href="/Archive2/Place/{$place.tid}"{if $place.name} data-title="{$place.name|escape}"{/if}>
						<img src="{if $place.thumbnail}{$place.thumbnail|escape}{else}/interface/themes/responsive/images/places.png{/if}"
						     alt="{$place.name|escape}">
					</a>
					<figcaption class="explore-more-category-title">
						<strong>{$place.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
						{if $place.relation_label} ({$place.relation_label|stripRelatorCode|escape}){/if}
						{* Use a regular space (instead of &nbsp; so that the label can wrap*}
					</figcaption>
				</figure>
			{/foreach}
		</div>
	{/if}
{/strip}