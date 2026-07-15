{if $searchId}
	<li class="breadcrumb-item">
		{translate text="Catalog Search"}
	</li>
	<li class="breadcrumb-item">
		<em aria-current="page">{if $lookfor == ""}All results{else}{$lookfor|capitalize|escape:"html"}{/if}</em>
	</li>
{elseif $pageTemplate!=""}
	<li class="breadcrumb-item">{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate}</li>
{/if}
