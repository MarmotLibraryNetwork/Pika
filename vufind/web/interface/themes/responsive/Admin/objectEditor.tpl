<script src="/interface/themes/responsive/js/ckeditor/ckeditor.js">
</script>
{if $lastError}
	<div class="alert alert-danger">
		{$lastError}
	</div>
{/if}
{strip}
	<div class="col-xs-12">
		{if $shortPageTitle || $objectName}
			<h1 role="heading" aria-level="1" class="h2">{if $shortPageTitle}{$shortPageTitle}{/if}{if $shortPageTitle && $objectName} - {/if}{$objectName}</h1>
		{/if}
		<p>
			{if $showReturnToList}
				<a class="btn btn-default" href='/{$module}/{$toolName}?objectAction=list'>Return to List</a>
			{/if}
			{if $id > 0 && $canDelete}<a class="btn btn-danger" href='/{$module}/{$toolName}?id={$id}&amp;objectAction=delete' onclick='return confirm("Are you sure you want to delete this {$objectType}?")'>Delete</a>{/if}
		</p>
		<div class="btn-group">
			{foreach from=$additionalObjectActions item=action}
				{if empty($action.url)} {* For accessibility, use buttons instead of <a> when there is no URL *}
					<button class="btn btn-default btn-sm"{if $action.onclick} onclick="{$action.onclick}"{/if}>{$action.text}</button>
				{else}
				<a class="btn btn-default btn-sm"{if $action.url} href='{$action.url}'{/if}{if $action.onclick} onclick="{$action.onclick}"{/if}>{$action.text}</a>
				{/if}
			{/foreach}
		</div>
		{include file="DataObjectUtil/objectEditForm.tpl"}
	</div>
{/strip}