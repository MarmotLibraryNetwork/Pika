<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  {if strlen($librarySystemName) < 16}<ShortName>{$librarySystemName}</ShortName>{/if}
  <LongName>{$librarySystemName}</LongName>
  <Description>Library Catalog Search</Description>
  <Image height="64" width="64" type="image/png">{$url}{img filename=favicon.png}</Image>
	<InputEncoding>UTF-8</InputEncoding>
  <Contact>{$supportEmail}</Contact>
  <Developer>Marmot Library Network</Developer>
  <Attribution>Copyright 2023, Marmot Library Network, All Rights Reserved</Attribution>
  <SyndicationRight>{if $productionServer}open{else}closed{/if}</SyndicationRight>
  <Url type="text/html" method="get" template="{$url}/Search/Results?lookfor={literal}{searchTerms}&amp;page={startPage?}{/literal}" />
  <Url type="application/rss+xml" method="get" template="{$url}/Search/Results?lookfor={literal}{searchTerms}{/literal}&amp;view=rss" />
  <Url type="application/json" rel="suggestions" method="get" template="{$url}/Search/Suggest?lookfor={literal}{searchTerms}{/literal}&amp;format=JSON" />
</OpenSearchDescription>
