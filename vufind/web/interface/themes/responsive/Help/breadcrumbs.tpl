<li class="breadcrumb-item">
	{if $shortPageTitle}
	<em>{$shortPageTitle}</em>
	{else}
	<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
	{/if}

</li>