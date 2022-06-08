{strip}
	{foreach from=$obituaries item=obituary}
		<p class="obituaryTitle">
			{$obituary->source}{if $obituary->sourcePage} page {$obituary->sourcePage}{/if}{if $obituary->formattedObitDate()} - {$obituary->formattedObitDate()}{/if}
			{if $userIsAdmin}
				<div class="btn-toolbar">
					<a href='/Admin/Obituaries?objectAction=edit&amp;id={$obituary->obituaryId}' title='Edit this Obituary' class='btn btn-default'>
						Edit
					</a>
					<a href='/Admin/Obituaries?objectAction=delete&amp;id={$obituary->obituaryId}' title='Delete this Obituary' onclick='return confirm("Removing this obituary will permanently remove it from the system.	Are you sure?")' class='btn btn-sm btn-danger'>
						Delete
					</a>
				</div>
			{/if}
		</p>
      {if $obituary->picture}
				<p class="obituaryPicture">{if $obituary->picture|escape}<a href="{$obituary->getImageUrl('large')}"><img class="obitPicture" src="{$obituary->getImageUrl('medium')}" alt="Image of Obituary Text"></a>{/if}</p>
				<div class="clearer"></div>
      {/if}
      {if $obituary->contents}
				<p class="obituaryText">{$obituary->contents|escape|replace:"\r":"<br>"}</p>
				<div class="clearer"></div>
      {/if}
	{/foreach}
{/strip}