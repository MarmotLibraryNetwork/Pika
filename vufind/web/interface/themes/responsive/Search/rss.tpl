<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
{strip}
	<channel>
		<title>Results For {$lookfor|escape}</title>
		{if $result.responseHeader.params.rows}
			<description>Displaying the first {$result.responseHeader.params.rows} search results of {$result.response.numFound} found.</description>
		{else}
			<description>Displaying {$result.response.docs|count} search results.</description>
		{/if}
		<link>{$searchUrl|escape}</link>

		{foreach from=$result.response.docs item="doc"}
			<item>
				<title>{$doc.title_display|escape}</title>
				<link>{$doc.recordUrl|escape}</link>
				{if $doc.author_display}
					<author>{$doc.author_display|escape}</author>
				{/if}
				<guid isPermaLink="true">{$doc.recordUrl|escape}</guid>
				{if $doc.rss_date}
					<pubDate>{$doc.rss_date}</pubDate>
				{/if}
				{if $doc.rss_description}
				<description>{$doc.rss_description|escape}</description>
				{/if}
			</item>
		{/foreach}
	</channel>
</rss>
{/strip}
