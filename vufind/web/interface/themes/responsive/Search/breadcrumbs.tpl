{if $searchId}
	<li class="breadcrumb-item">
		{translate text="Catalog Search"}
	</li>
	<li class="breadcrumb-item active" aria-current="page">
		<em>{if $lookfor == ""}All results{else}{$lookfor|capitalize|escape:"html"}{/if}</em>
	</li>
{elseif $pageTemplate!=""}
	<li class="breadcrumb-item active" aria-current="page">{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate}</li>
{/if}
