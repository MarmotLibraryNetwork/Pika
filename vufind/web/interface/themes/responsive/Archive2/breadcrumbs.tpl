{strip}
	<li>
		<a href="/Archive2/Home">{translate text="Archive Home"}</a>
		<span class="divider">&raquo;</span>
	</li>
	{* searchResultsUrl is rebuilt from the saved search this record was reached through (see
	   SearchObject_Islandora2::getNextPrevLinks()); lastsearch, the last archive search of the
	   session, stands in when there is no saved search to work from.  Kept in step with
	   Archive2/search-results-navigation.tpl, which links back to the same results. *}
	{assign var="returnToSearchUrl" value=$searchResultsUrl|default:$lastsearch}
	{if $returnToSearchUrl}
		<li>
			<a href="{$returnToSearchUrl|escape}">{translate text="Archive Search Results"}</a>
			<span class="divider">&raquo;</span>
		</li>
	{/if}
	{if $parent_title}
		<li>
			<span>
				{if $parent_rel_url}<a href="{$parent_rel_url}">{/if}
				{$parent_title|escape}
				{if $parent_rel_url}</a>{/if}
			</span>
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