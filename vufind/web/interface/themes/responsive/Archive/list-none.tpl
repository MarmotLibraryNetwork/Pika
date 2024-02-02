{strip}
<h2>{translate text='nohit_heading'}</h2>

<p class="alert alert-info">{translate text='nohit_prefix'} - <b>{if $lookfor}{$lookfor|escape:"html"}{else}&lt;empty&gt;{/if}</b> - {translate text='nohit_suffix'}</p>

{if $parseError}
  <p class="error">{translate text='nohit_parse_error'}</p>
{/if}

{* Search Debugging *}
{include file="Search/search-debug.tpl"}


{if $spellingSuggestions}
<div class="correction">{translate text='nohit_spelling'}:<br>
{foreach from=$spellingSuggestions item=details key=term name=termLoop}
  {$term|escape} &raquo; {foreach from=$details.suggestions item=data key=word name=suggestLoop}<a href="{$data.replace_url|escape}">{$word|escape}</a>{if $data.expand_url} <a href="{$data.expand_url|escape}"><img src="/images/silk/expand.png" alt="{translate text='spell_expand_alt'}"/></a> {/if}{if !$smarty.foreach.suggestLoop.last}, {/if}{/foreach}{if !$smarty.foreach.termLoop.last}<br>{/if}
{/foreach}
</div>
<br>
{/if}
{/strip}
{if $showExploreMoreBar}
  <div id="explore-more-bar-placeholder"></div>
  <script>
    $(function(){ldelim}
        Pika.Searches.loadExploreMoreBar('{$exploreMoreSection}', '{$exploreMoreSearchTerm|escape:"html"}');
      {rdelim});
  </script>
{/if}
