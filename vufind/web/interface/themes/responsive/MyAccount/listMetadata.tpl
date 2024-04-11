{strip}
	<meta property="title" content="{$semanticData->title|escape:"html"}">
	<meta property="description" content="{$semanticData->description|strip_tags|escape}">
	<meta property="og:description" content="{$semanticData->description|strip_tags|escape}">
	<meta property="og:image" content="/bookcover.php?id={$semanticData->id}&size=large&type=userList">
{/strip}