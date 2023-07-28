{strip}
	<div id="main-content">
		<h1>{$shortPageTitle}{if $shortPageTitle && $objectName} - {/if}{$objectName}</h1>
		<div class="btn-group">
			<a class="btn btn-sm btn-default" href="/Admin/TranslationMaps?objectAction=edit&amp;id={$id}">Edit Map</a>
				{foreach from=$additionalObjectActions item=action}
						{if $smarty.server.REQUEST_URI != $action.url}
							<a class="btn btn-default btn-sm" href='{$action.url}'>{$action.text}</a>
						{/if}
				{/foreach}
			<a class="btn btn-sm btn-default" href='/Admin/TranslationMaps?objectAction=list'>Return to List</a>
		</div>
		<p>
			{foreach from=$translationMapValues item=translationMapValue}
				{$translationMapValue->value} = {$translationMapValue->translation}<br>
			{/foreach}
		</p>
	</div>
{/strip}