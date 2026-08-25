{* Archive2 — Shared First/Previous/Page X of Y/Next/Last pager for paginated
   collection and search-result grids. Callers assign $page, $pageCount, and
   $pagerUrlTemplate (a URL containing a literal "%d" placeholder for the page
   number) before including this. *}
{if $pageCount > 1}
	{assign var="prevPage" value=$page-1}
	{assign var="nextPage" value=$page+1}
	<div class="text-center">
		<ul class="pagination collection-pager">
			{if $page > 1}
				<li><a href="{$pagerUrlTemplate|replace:'%d':1}">&laquo; First</a></li>
				<li><a href="{$pagerUrlTemplate|replace:'%d':$prevPage}">&lsaquo; Previous</a></li>
			{/if}
			<li class="disabled"><span>Page {$page} of {$pageCount}</span></li>
			{if $page < $pageCount}
				<li><a href="{$pagerUrlTemplate|replace:'%d':$nextPage}">Next &rsaquo;</a></li>
				<li><a href="{$pagerUrlTemplate|replace:'%d':$pageCount}">Last &raquo;</a></li>
			{/if}
		</ul>
	</div>
{/if}
