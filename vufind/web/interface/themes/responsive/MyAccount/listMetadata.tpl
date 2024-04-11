{strip}
	<meta property="title" content="{$semanticData->title|escape:"html"}">
	<meta property="image" content="{$url}/bookcover.php?id={$semanticData->id}&size=large&type=userList">
	<meta property="og:title" content="{$semanticData->title|escape:"html"}">
	{if $semanticData->description}
		<meta property="og:description" content="{$semanticData->description|strip_tags|escape}">
		<meta property="description" content="{$semanticData->description|strip_tags|escape}">
	{/if}
	<meta property="og:image" content="{$url}/bookcover.php?id={$semanticData->id}&size=large&type=userList">
{/strip}