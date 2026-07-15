<li class="breadcrumb-item active" aria-current="page">
	{if $shortPageTitle}
	<em>{$shortPageTitle}</em>
	{else}
	<em>{$pageTemplate|replace:'.tpl':''|capitalize|translate}</em>
	{/if}

</li>