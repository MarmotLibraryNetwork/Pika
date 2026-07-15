{strip}
	<li class="breadcrumb-item">
		<a href="/Archive2/Home">{translate text="Archive Home"}</a>

	</li>
	{if $lastsearch}
		<li class="breadcrumb-item">
			<a href="{$lastsearch|escape}">{translate text="Archive Search Results"}</a>

		</li>
	{/if}
	{if $parent_title}
		<li class="breadcrumb-item">
			<span>
				{if $parent_rel_url}<a href="{$parent_rel_url}">{/if}
				{$parent_title|escape}
				{if $parent_rel_url}</a>{/if}
			</span>

		</li>
	{/if}
	{if $display_model}
		<li class="breadcrumb-item">
			<span>{$display_model|escape}</span>

		</li>
	{elseif $vocabulary_label}
		<li class="breadcrumb-item">
			<span>{$vocabulary_label|escape}</span>

		</li>
	{/if}

	{if $breadcrumbText}
		<li class="breadcrumb-item active" aria-current="page">
			<em>{$breadcrumbText|truncate:30:"..."|escape}</em>
		</li>
	{/if}
{/strip}