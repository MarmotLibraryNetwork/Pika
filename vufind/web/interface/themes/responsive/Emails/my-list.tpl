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
			{$url}{$title.url}
		{/if}
{/if}

{foreach from=$listEntries item=listEntry}
{*If the listEntry has a note see if it is the same work*}
{if $listEntry->notes && ($listEntry->groupedWorkPermanentId == $title.id || $listEntry->groupedWorkPermanentId == $title.PID)}
{translate text="Notes"}: {$listEntry->notes}

{/if}
{/foreach}
---------------------
{/foreach}
{/if}

