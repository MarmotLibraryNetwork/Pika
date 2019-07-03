{strip}
	{if $recordDriver}
	<div class="row">
		<div class="result-label col-md-2">{translate text='Tags'}:</div>
		<div class="result-value col-md-10">
			{if $recordDriver->getTags()}
				{foreach from=$recordDriver->getTags() item=tag name=tagLoop}
					<a href="{$path}/Search/Results?tag={$tag->tag|escape:"url"}">{$tag->tag|escape:"html"}</a> <span class="badge btn-info">{$tag->cnt}</span>
					{if $tag->userAddedThis}
						&nbsp;<a onclick="return VuFind.GroupedWork.removeTag('{$recordDriver->getPermanentId()|escape}', '{$tag->tag}');" class="btn btn-xs btn-danger">
							Delete
						</a>
					{/if}
					<br/>
				{/foreach}
			{else}
				<p class="alert alert-info">
					{translate text='No Tags'}, {translate text='Be the first to tag this record'}!
				</p>
			{/if}

			<br/>
			<div>
				<a href="#" onclick="return VuFind.GroupedWork.showTagForm(this, '{$recordDriver->getPermanentId()|escape}');" class="btn btn-sm btn-default">
					{translate text="Add Tag"}
				</a>
			</div>
		</div>

	</div>
	{/if}
{/strip}