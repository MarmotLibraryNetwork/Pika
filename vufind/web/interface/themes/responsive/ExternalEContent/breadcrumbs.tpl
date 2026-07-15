{strip}
	{if $lastsearch}
		<li class="breadcrumb-item">
			{if $lastsearch}
				<a href="{$lastsearch|escape}#record{$id|escape:"url"}">{translate text="Return to Search Results"}</a>

			{else}
				Catalog
			{/if}
		</li>
	{/if}
	{if $recordDriver}
		<li class="breadcrumb-item active" aria-current="page">
			<a href="/GroupedWork/{$recordDriver->getPermanentId()}">{$recordDriver->getBreadcrumb()|truncate:30:"..."|escape}</a>

		</li>
		{if $recordDriver->getFormats()}
			<li class="breadcrumb-item">
				&nbsp;<em>{implode subject=$recordDriver->getFormats() glue=", "}</em>
			</li>
		{/if}
	{/if}

{/strip}