{strip}
	{if $showFavorites == 1}
		<div class="text-center row">
			<div class="col-sm-12">
				<button type="button" onclick="return Pika.GroupedWork.showSaveToListForm(this, '{$recordDriver->getPermanentId()|escape}');" class="btn btn-sm addtolistlink">{translate text='Add to favorites'}</button>
			</div>
		</div>
	{/if}
	<div class="text-center row">
		{include file="GroupedWork/share-tools.tpl"}
	</div>
{/strip}