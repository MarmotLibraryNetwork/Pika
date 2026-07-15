{if $searchId}
	<li class="breadcrumb-item">Genealogy {translate text="Search"}</li>
	<li class="breadcrumb-item active" aria-current="page">{$lookfor|capitalize|escape:"html"}</li>
{elseif $pageTemplate!=""}
	<li class="breadcrumb-item active" aria-current="page">{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate}</li>
{/if}
