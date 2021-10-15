{* This is a text-only email template; do not include HTML! *}
{$seriesTitle}
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
{foreach from=$seriesData item=title}

{if $title.title}{$title.title}
{$title.series}:	{$title.volume}
{$title.author}
{$url}{$title.fullRecordLink}
{/if}
---------------------
{/foreach}
{/if}