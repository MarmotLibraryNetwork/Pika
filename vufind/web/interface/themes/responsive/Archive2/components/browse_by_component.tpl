{strip}
<div class="archiveComponentContainer nopadding col-sm-12">
	<div class="archiveComponent horizontalComponent">
		<div class="archiveComponentBody" style="min-height: 14px;">
			<div class="archiveComponentBox">
				<div class="archiveComponentHeader">{$browseByTitle}</div>
				<div class="archiveComponentLinks row">
					<div class="col-sm-6">
						{foreach from=$browseByColumn1 item=item}
						<div><a href="{$item.url}">{$item.name|escape}</a></div>
						{/foreach}
					</div>
					<div class="col-sm-6">
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
