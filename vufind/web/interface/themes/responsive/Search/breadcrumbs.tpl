{if $searchId}
	<li>
		{translate text="Catalog Search"} <span class="divider">&raquo;</span>
	</li>
	<li>
		<em aria-current="page">{if $lookfor == ""}All results{else}{$lookfor|capitalize|escape:"html"}{/if}</em> <span class="divider">&raquo;</span>
	</li>
{elseif $pageTemplate!=""}
	<li>{translate text=$pageTemplate|replace:'.tpl':''|capitalize|translate} <span class="divider">&raquo;</span></li>
{/if}
