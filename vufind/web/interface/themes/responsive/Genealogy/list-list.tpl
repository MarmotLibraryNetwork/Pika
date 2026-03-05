{foreach from=$recordSet item=record}
  <div class="result {if ($record@iteration % 2) == 0}alt{/if} record{$record@iteration}">
    {* This is raw HTML -- do not escape it: *}
    {$record}
  </div>
{/foreach}

{if $userIsAdmin}
<a href='/Admin/People?objectAction=addNew' class='btn btn-sm btn-info'>Add someone new</a>
{/if}
