{strip}
{if $relatedObjects}
<div id="taxonomy-related-objects" class="taxonomy-related-objects">
	<h2 class="h3">Related Items</h2>
	<div class="row">
		{foreach from=$relatedObjects item=obj}
		<div class="col-sm-12 col-md-6 col-lg-4 col-xl-3">
			<div class="panel panel-default taxonomy-related-item">
				<div class="panel-body text-center">
					{if $obj.thumbnailUrl}
						<a href="{$obj.url}">
							<img src="{$obj.thumbnailUrl}"
							     alt="{$obj.title|escape}"
							     class="img-responsive taxonomy-related-thumb"
							 />
						</a>
					{/if}
					<a href="{$obj.url}">{$obj.title|escape}</a>
				</div>
			</div>
		</div>
		{/foreach}
	</div>
</div>
{/if}
{/strip}
