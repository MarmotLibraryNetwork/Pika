From: {$name}
Email: {$email}
Library: {$libraryName}
{if !empty($card)} Library Card: {$card} {/if}
{if !empty($browser)}Browser: {$browser}{/if}

Report Description:
{$report}

______
Submitted to site: {$url}
User Agent: {$smarty.server.HTTP_USER_AGENT}
