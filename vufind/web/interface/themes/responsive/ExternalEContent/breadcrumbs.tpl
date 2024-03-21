{strip}
	{if $lastsearch}
		<li>
			{if $lastsearch}
				<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a>
				<span class="divider">&raquo;</span>
			{else}
				Catalog
			{/if}
		</li>
	{/if}
	{if $recordDriver}
		<li>
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}" aria-current="page">{$recordDriver->getBreadcrumb()|truncate:30:"..."|escape}</a>
			<span class="divider">&raquo;</span>
		</li>
		{if $recordDriver->getFormats()}
			<li>
				&nbsp;<em>{implode subject=$recordDriver->getFormats() glue=", "}</em> <span class="divider">&raquo;</span>
			</li>
		{/if}
	{/if}

{/strip}