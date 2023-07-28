{strip}
	{* Requires $summId & $summUrl to be defined by calling template *}
	<div class="result-tools-horizontal btn-toolbar" role="toolbar">
		{if $showMoreInfo !== false && $summUrl}
			<div class="btn-group btn-group-sm">
				<a href="{$summUrl}" class="btn btn-sm ">More Info</a>
			</div>
		{/if}
		{if $showFavorites == 1}
			<div class="btn-group btn-group-sm">
				<button onclick="return Pika.Archive.showSaveToListForm(this, '{$summId|escape}');" class="btn btn-sm ">{translate text='Add to favorites'}</button>
			</div>
		{/if}

	</div>

{/strip}