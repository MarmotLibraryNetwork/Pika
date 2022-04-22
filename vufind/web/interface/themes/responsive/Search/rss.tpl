{*{strip}*}<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
	<atom:link href="{$url|escape:'html':'UTF-8'}{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" rel="self" type="application/rss+xml" title="This RSS Feed's URL" />
	<channel>
		<title>Results For {$lookfor|escape:'html':'UTF-8'}</title>
		{if $result.responseHeader.params.rows}
			<description>Displaying the first {$result.responseHeader.params.rows} search results of {$result.response.numFound} found.</description>
		{else}
			<description>Displaying {$result.response.docs|count} search results.</description>
		{/if}
		<link>{$searchUrl|escape:'html':'UTF-8'}</link>

		{foreach from=$result.response.docs item="doc"}
			<item>
				<title>{$doc.title_display|escape:'html':'UTF-8'}</title>
				<link>{$doc.recordUrl|escape:'html':'UTF-8'}</link>
				{if $doc.author_display}
					<author>{$doc.author_display|escape:'html':'UTF-8'}</author>
				{/if}
				<guid isPermaLink="true">{$doc.recordUrl|escape:'html':'UTF-8'}</guid>
				{if $doc.rss_date}
					<pubDate>{$doc.rss_date}</pubDate>
				{/if}
				{if $doc.rss_description}
				<description>{$doc.rss_description|escape:'html':'UTF-8'}</description>
				{/if}
			</item>
		{/foreach}
	</channel>
</rss>
{*{/strip}*}
