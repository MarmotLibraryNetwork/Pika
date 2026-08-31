{strip}
{* This is a text-only email template; do not include HTML! *}
{$list->title}
{$list->description}
------------------------------------------------------------
{if !empty($message)}
{translate text="Message From Sender"}:
{$message}
------------------------------------------------------------
{/if}
{if $error}
{$error}
------------------------------------------------------------
{else}
{foreach from=$titles item=title}

{if $title.title_display}{$title.title_display}
	{$title.author_display}
	{$url}/GroupedWork/{$title.id}/Home
	{elseif $title.its_node_id}{$title.twm_X3b_en_title_ws_token[0]}
		{$title.format}
		{if $title.url}
		{$url}{$title.url}{* Keep the display to two tabs for URLS *}
		{/if}
	{elseif $title.its_tid}{$title.tm_X3b_en_name[0]}{* a taxonomy term carries no node id and keeps its name in a field of its own, so it is checked after its_node_id; its format is its vocabulary *}
		{$title.format}
		{if $title.url}
		{$url}{$title.url}{* Keep the display to two tabs for URLS *}
		{/if}
{/if}

{section name=listEntry loop=$listEntries}
{*If the listEntry has a note see if it is the same work. An archive document's Solr id is its uniqueKey rather than the id the list stores, so it is matched on listEntryId instead - the node id for an object, tax_vocabulary:tid for a taxonomy term.*}
{if $listEntries[listEntry]->notes && ($listEntries[listEntry]->groupedWorkPermanentId == $title.id || $listEntries[listEntry]->groupedWorkPermanentId == $title.PID || $listEntries[listEntry]->groupedWorkPermanentId == $title.listEntryId)}
{translate text="Notes"}: {$listEntries[listEntry]->notes}

{/if}
{/section}
---------------------
{/foreach}
{/if}
{/strip}
