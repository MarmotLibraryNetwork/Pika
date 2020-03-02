{strip}
	{foreach from=$obituaries item=obituary}
		<div class="obituaryTitle">
			{$obituary.source}{if $obituary.sourcePage} page {$obituary.sourcePage}{/if}{if $obituary.formattedObitDate} - {$obituary.formattedObitDate}{/if}
			{if $userIsAdmin}
				<div class="btn-toolbar">
					<a href='/Admin/Obituaries?objectAction=edit&amp;id={$obituary.obituaryId}' title='Edit this Obituary' class='btn btn-xs btn-default'>
						Edit
					</a>
					<a href='/Admin/Obituaries?objectAction=delete&amp;id={$obituary.obituaryId}' title='Delete this Obituary' onclick='return confirm("Removing this obituary will permanently remove it from the system.	Are you sure?")' class='btn btn-xs btn-danger'>
						Delete
					</a>
				</div>
			{/if}
		</div>
      {if $obituary.contents && $obituary.picture}
				<div class="obituaryText">{if $obituary.picture|escape}<a href="{$obituary->getImageUrl('large')}"><img class="obitPicture" src="{$obituary->getImageUrl('medium')}"></a>{/if}{$obituary.contents|escape}</div>
				<div class="clearer"></div>
      {elseif $obituary.contents}
				<div class="obituaryText">{$obituary.contents|escape|replace:"\r":"<br>"}</div>
				<div class="clearer"></div>
      {elseif $obituary.picture}
				<div class="obituaryPicture">{if $obituary.picture|escape}<a href="{$obituary->getImageUrl('large')}"><img class="obitPicture" src="{$obituary->getImageUrl('medium')}"></a>{/if}</div>
				<div class="clearer"></div>
      {/if}
	{/foreach}
{/strip}