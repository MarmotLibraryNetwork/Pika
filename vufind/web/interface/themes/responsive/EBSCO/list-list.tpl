{foreach from=$recordSet item=record}
  <div class="result {if ($record@iteration % 2) == 0}alt{/if} record{$record@iteration}">
    {* This is raw HTML -- do not escape it: *}
    {$record}
  </div>
  {if $showExploreMoreBar && ($record@iteration == 2 || count($recordSet) < 2)}
    <div id="explore-more-bar-placeholder"></div>
    <script>
      $(function(){ldelim}
          Pika.Searches.loadExploreMoreBar('ebsco', '{$exploreMoreSearchTerm|escape:"html"}');
        {rdelim}
      );
    </script>
  {/if}
{/foreach}
