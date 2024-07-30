{strip}
	<meta property="title" content="List - {$listSemanticData->title|escape:"html"}">
	<meta property="image" content="{$url}/bookcover.php?id={$listSemanticData->id}&size=medium&type=userList">
	<meta property="og:title" content="List - {$listSemanticData->title|escape:"html"}">
	<meta property="og:url" content="{$url}/MyAccount/MyList/{$listSemanticData->id}">
	{if $listSemanticData->description}
		<meta property="og:description" content="{$listSemanticData->description|strip_tags|escape}">
		<meta property="description" content="{$listSemanticData->description|strip_tags|escape}">
	{/if}
	<meta property="og:image" content="{$url}/bookcover.php?id={$listSemanticData->id}&size=medium&type=userList">
{/strip}