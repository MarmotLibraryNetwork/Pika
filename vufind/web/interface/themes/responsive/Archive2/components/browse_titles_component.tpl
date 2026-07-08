{strip}
<div class="archiveComponentContainer nopadding col-sm-12">
	<div class="archiveComponent horizontalComponent">
		<div class="archiveComponentBody">
			<div class="archiveComponentBox">
				<div class="archiveComponentHeader">{$browseCollectionTitle}</div>
				<div class="archiveComponentLinks row">
					{foreach from=$browseCollectionItems item=item}
					<div class="col-tn-12">
						<a href="{$item.url}">{$item.title}</a>
					</div>
					{/foreach}
				</div>
			</div>
		</div>
	</div>
</div>
{/strip}
