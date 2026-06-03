{strip}
	{if $related_event}
		<div class="related-objects results-covers home-page-browse-thumbnails">
			{foreach from=$related_event item=event}
				<figure class="browse-thumbnail-sorted">
					<a href="/Archive2/Event/{$event.tid}"{if $event.name} data-title="{$event.name|escape}"{/if}>
						<img src="{if $event.thumbnail}{$event.thumbnail|escape}{else}/interface/themes/responsive/images/events.png{/if}"
						     alt="{$event.name|escape}">
					</a>
					<figcaption class="explore-more-category-title">
						<strong>{$event.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
						{if $event.relation_label} ({$event.relation_label|stripRelatorCode|escape}){/if}
					</figcaption>
				</figure>
			{/foreach}
		</div>
	{/if}
{/strip}