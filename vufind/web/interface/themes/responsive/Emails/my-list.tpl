{* Text-only email template - do not include HTML.

   Every line break and space outside a tag is part of the email text, so this file cannot be
   indented for readability the way a normal template is. Two rules keep the output predictable:

   1. A detail line is indented with exactly four literal spaces, at the start of a source line.
      Four spaces rather than a tab because a tab renders anywhere from four to eight columns
      depending on the mail client, and some clients collapse it.
   2. Anywhere a tag must not add a line break, the break and any indentation after it are
      wrapped in a Smarty comment, which emits nothing. That is why some tags are joined and why
      the if and endif around the URL sit at column 0 - an output line's own leading break has to
      survive, and indentation would follow it.

   Do not wrap this file in a strip block. Strip removes the line breaks that make the layout,
   not just the stray ones. The closing of this comment is joined to the title below for the same
   reason - on its own line it would put a blank line at the top of the email. *}{$list->title}
{$list->description}
------------------------------------------------------------
{if !empty($message)}{translate text="Message From Sender"}:
{$message}
------------------------------------------------------------
{/if}{*
*}{if $error}{$error}
------------------------------------------------------------
{else}{*
*}{foreach from=$titles item=title}
{if $title.title_display}{$title.title_display}
    {$title.author_display}
    {$url}/GroupedWork/{$title.id}/Home
{elseif $title.its_node_id}{$title.twm_X3b_en_title_ws_token[0]}
    {$title.format}
{if $title.url}    {$url}{$title.url}
{/if}{*
    A taxonomy term carries no node id and keeps its name in a field of its own, so it is checked
    after its_node_id and an object document always wins. Its format is its vocabulary.
*}{elseif $title.its_tid}{$title.tm_X3b_en_name[0]}
    {$title.format}
{if $title.url}    {$url}{$title.url}
{/if}{/if}{*
*}{section name=listEntry loop=$listEntries}{*
    If the list entry has a note, see whether it belongs to this work. An archive document's Solr
    id is its uniqueKey rather than the id the list stores, so it is matched on listEntryId - the
    node id for an object, tax_vocabulary:tid for a taxonomy term.
*}{if $listEntries[listEntry]->notes && ($listEntries[listEntry]->groupedWorkPermanentId == $title.id || $listEntries[listEntry]->groupedWorkPermanentId == $title.PID || $listEntries[listEntry]->groupedWorkPermanentId == $title.listEntryId)}    {translate text="Notes"}: {$listEntries[listEntry]->notes}
{/if}{/section}---------------------
{/foreach}{/if}
