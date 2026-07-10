{strip}
<div class="archiveComponentContainer nopadding col-md-12">
	<div class="archiveComponent horizontalComponent">
		<div class="archiveComponentBody browse-by-component-body">
			<div class="archiveComponentBox">
				<div class="archiveComponentHeader">{$browseByTitle}</div>
				<div class="archiveComponentLinks row">
					<div class="col-md-6">
						{foreach from=$browseByColumn1 item=item}
						<div><a href="{$item.url}">{$item.name|escape}</a></div>
						{/foreach}
					</div>
					<div class="col-md-6">
						{foreach from=$browseByColumn2 item=item}
						<div><a href="{$item.url}">{$item.name|escape}</a></div>
						{/foreach}
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
{/strip}
