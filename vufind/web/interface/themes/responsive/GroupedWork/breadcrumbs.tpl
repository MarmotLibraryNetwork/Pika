{if $lastsearch}
&nbsp;<a href="{$lastsearch|escape}#record{$recordDriver->getPermanentId()|escape:"url"}">{translate text="Catalog Search Results"}</a> <span class="divider">&raquo;</span>
{/if}
{if $breadcrumbText}
&nbsp;<em>{$breadcrumbText|truncate:30:"..."|escape}</em> <span class="divider">&raquo;</span>
{/if}
{if $subTemplate!=""}
&nbsp;<em>{$subTemplate|replace:'view-':''|replace:'.tpl':''|replace:'../MyResearch/':''|capitalize|translate}</em>
{/if}
{if $action == "Series"}
	{if $lookfor}
		<a href="{$url}/Union/Search?basicType=Series&lookfor={$lookfor}">Series</a>
	{else}
			Series
		{/if}
	<span class="divider">&raquo;</span><em>{$pageTitleShort}</em>
		{/if}