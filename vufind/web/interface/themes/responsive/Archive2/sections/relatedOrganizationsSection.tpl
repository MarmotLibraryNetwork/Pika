{strip}
	{if $related_organization}
		<div class="related-objects results-covers home-page-browse-thumbnails">
			{foreach from=$related_organization item=org}
				<figure class="browse-thumbnail-sorted">
					<a href="/Archive2/Organization/{$org.tid}"{if $org.name} data-title="{$org.name|escape}"{/if}>
						<img src="{if $org.thumbnail}{$org.thumbnail|escape}{else}/interface/themes/responsive/images/organization.png{/if}"
						     alt="{$org.name|escape}">
					</a>
					<figcaption class="explore-more-category-title">
						<strong>{$org.name|escape|removeTrailingPunctuation|truncate:60:"..."}</strong>
						{if $org.relation_label} ({$org.relation_label|stripRelatorCode|escape}){/if}
					</figcaption>
				</figure>
			{/foreach}
		</div>
	{/if}
{/strip}