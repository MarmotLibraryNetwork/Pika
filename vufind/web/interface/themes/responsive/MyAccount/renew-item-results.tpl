{strip}
<div class="contents">
	{if $renewResults.success}
		<div class="alert alert-success">{$renewResults.message}</div>
	{else}
		<div class="alert alert-danger">{$renewResults.message}</div>
	{/if}
</div>
}
{/strip}