{if $searchId}
	<li class="breadcrumb-item">Genealogy {translate text="Search"}</li>
	<li class="breadcrumb-item">{$lookfor|capitalize|escape:"html"}</li>
{elseif $pageTemplate!=""}
	<li class="breadcrumb-item">{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate}</li>
{/if}
