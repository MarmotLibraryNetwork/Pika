{strip}
	{if $recordDriver}
	<div class="row">
		<div class="result-label col-md-2">{translate text='Tags'}:</div>
		<div class="result-value col-md-10">
			{if $recordDriver->getTags()}
				{foreach from=$recordDriver->getTags() item=tag name=tagLoop}
					<a href="/Search/Results?tag={$tag->tag|escape:"url"}">{$tag->tag|escape:"html"}</a> <span class="badge btn-info">{$tag->cnt}</span>
					{if $tag->userAddedThis}
						&nbsp;<button onclick="return Pika.GroupedWork.removeTag('{$recordDriver->getPermanentId()|escape}', '{$tag->tag}');" class="btn btn-sm btn-danger">
							Delete
						</button>
					{/if}
					<br>
				{/foreach}
			{else}
				<p class="alert alert-info">
					{translate text='No Tags'}, {translate text='Be the first to tag this record'}!
				</p>
			{/if}
			<br>
			<div>
				<button onclick="return Pika.GroupedWork.showTagForm(this, '{$recordDriver->getPermanentId()|escape}');" class="btn btn-default">
					{translate text="Add Tag"}
				</button>
			</div>
		</div>

	</div>
	{/if}
{/strip}