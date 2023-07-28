{if $user->webNote}
	{if is_array($user->webNote)}
		{foreach from=$user->webNote item="webNote"}
			<div class="row">
				<div {*id="webNote"*} class="alert alert-info text-center col-xs-12">{$webNote}</div>
			</div>
		{/foreach}
	{else}
		<div class="row">
			<div id="webNote" class="alert alert-info text-center col-xs-12">{$user->webNote}</div>
		</div>
	{/if}
{/if}
