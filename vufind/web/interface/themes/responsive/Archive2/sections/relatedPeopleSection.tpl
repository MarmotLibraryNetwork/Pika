{strip}
	{if $related_person}
		<div class="related-objects results-covers home-page-browse-thumbnails">
			{foreach from=$related_person item=person}
				<figure class="browse-thumbnail-sorted">
					<a href="/Archive2/Person/{$person.tid}"{if $person.name} data-title="{$person.name|escape}"{/if}>
						<img src="{if $person.thumbnail}{$person.thumbnail|escape}{else}/interface/themes/responsive/images/people.png{/if}"
						     alt="{$person.name|escape}">
					</a>
					<figcaption class="explore-more-category-title">
						<strong>{$person.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
						{if $person.relation_label} ({$person.relation_label|stripRelatorCode|escape}){/if}
						{* Use a regular space (instead of &nbsp; so that the label can wrap*}
					</figcaption>
				</figure>
			{/foreach}
		</div>
	{/if}
{/strip}