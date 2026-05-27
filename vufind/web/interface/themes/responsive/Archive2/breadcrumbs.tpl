{strip}
	<li>
		<a href="/Archive2/Home">{translate text="Archive Home"}</a>
		<span class="divider">&raquo;</span>
	</li>
	{if $lastsearch}
		<li>
			<a href="{$lastsearch|escape}">{translate text="Archive Search Results"}</a>
			<span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $parent_title}
		<li>
	<span>{if $parent_rel_url}<a href="{$parent_rel_url}">{/if}
			{$parent_title|escape}
			</span>
			{if $parent_rel_url}</a>{/if}
			<span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $display_model}
		<li>
			<span>{$display_model|escape}</span>
			<span class="divider">&raquo;</span>
		</li>
	{elseif $vocabulary_label}
		<li>
			<span>{$vocabulary_label|escape}</span>
			<span class="divider">&raquo;</span>
		</li>
	{/if}

	{if $breadcrumbText}
		<li>
			<em aria-current="page">{$breadcrumbText|truncate:30:"..."|escape}</em>
		</li>
	{/if}
{/strip}