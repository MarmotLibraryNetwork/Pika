{strip}
	{if $tagList}
		<div class="panel">
			<a data-toggle="collapse" data-parent="#account-link-accordion" href="#myTagsPanel">
				<div class="panel-heading">
					<div class="panel-title collapsed">
						My Tags
					</div>
				</div>
			</a>
			<div id="myTagsPanel" class="panel-collapse collapse">
				<div class="panel-collapse">
					<div class="panel-body">
						{foreach from=$tagList item=tag}
							<div class="myAccountLink">
								<a href='/Search/Results?lookfor={$tag->tag|escape:"url"}&amp;basicType=tag'>{$tag->tag|escape:"html"}</a> ({$tag->cnt})&nbsp;
								<button class="btn btn-link" onclick="return Pika.Account.removeTag('{$tag->tag}');" title="Delete Tag">
								<span class="glyphicon glyphicon-remove-circle">&nbsp;</span>
								</button>
							</div>
						{/foreach}
					</div>
				</div>
			</div>
		</div>
	{/if}
{/strip}