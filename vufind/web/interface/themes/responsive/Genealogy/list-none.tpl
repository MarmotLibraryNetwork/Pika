{strip}
{* Recommendations *}
{if $topRecommendations}
	{foreach from=$topRecommendations item="recommendations"}
		{include file=$recommendations}
	{/foreach}
{/if}

<h1 role="heading" aria-level="1" class="h2">{translate text='nohit_heading'}</h1>
	<p class="alert alert-info">{translate text='nohit_prefix'} - <b>{$lookfor|escape:"html"}</b> - {translate text='nohit_suffix'}</p>

{* Search Debugging *}
{include file="Search/search-debug.tpl"}


{if $parseError}
	<div class="alert alert-danger">
		{$parseError}
	</div>
{/if}

{if $spellingSuggestions}
	<div class="correction">{translate text='nohit_spelling'}:<br>
	{foreach from=$spellingSuggestions item=details key=term name=termLoop}
		{$term|escape} &raquo; {foreach from=$details.suggestions item=data key=word name=suggestLoop}<a href="{$data.replace_url|escape}">{$word|escape}</a>{if $data.expand_url} <a href="{$data.expand_url|escape}"><img src="/images/silk/expand.png" alt="{translate text='spell_expand_alt'}"></a> {/if}{if !$smarty.foreach.suggestLoop.last}, {/if}{/foreach}{if !$smarty.foreach.termLoop.last}<br>{/if}
	{/foreach}
	</div>
	<br>
{/if}

{if $userIsAdmin}
	<a href="/Admin/People?objectAction=addNew" class="btn btn-sm btn-info">Add someone new</a>
{/if}
{/strip}