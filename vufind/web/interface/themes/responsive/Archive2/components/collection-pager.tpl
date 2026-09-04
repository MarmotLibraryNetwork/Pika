{* Archive2 — Shared First/Previous/Page X of Y/Next/Last pager for paginated
   collection and search-result grids. Callers assign $page, $pageCount, and
   $pagerUrlTemplate (a URL containing a literal "%d" placeholder for the page
   number) before including this. *}
{if $pageCount > 1}
	{assign var="prevPage" value=$page-1}
	{assign var="nextPage" value=$page+1}
	<nav class="d-flex justify-content-center" aria-label="{translate text='Collection pages'}">
		<ul class="pagination collection-pager">
			{if $page > 1}
				<li class="page-item"><a class="page-link" href="{$pagerUrlTemplate|replace:'%d':1}">&laquo; First</a></li>
				<li class="page-item"><a class="page-link" href="{$pagerUrlTemplate|replace:'%d':$prevPage}">&lsaquo; Previous</a></li>
			{/if}
			<li class="page-item disabled"><span class="page-link">Page {$page} of {$pageCount}</span></li>
			{if $page < $pageCount}
				<li class="page-item"><a class="page-link" href="{$pagerUrlTemplate|replace:'%d':$nextPage}">Next &rsaquo;</a></li>
				<li class="page-item"><a class="page-link" href="{$pagerUrlTemplate|replace:'%d':$pageCount}">Last &raquo;</a></li>
			{/if}
		</ul>
	</nav>
{/if}
