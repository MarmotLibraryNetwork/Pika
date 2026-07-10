{strip}
<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div class="archiveComponent browseFilterContainer">
		<div class="row archiveComponentBody">
			<div class="archiveComponentBox">
				<a href="#" data-bs-toggle="modal" data-bs-target="#browseRelatedModal{$browseRelatedId}">
					<div class="col-tn-4 col-xs-3 col-md-4 archiveComponentIconContainer">
						<img src="{$browseRelatedImage}" width="100" height="100" alt="{$browseRelatedTitle|escape}" class="archiveComponentImage">
					</div>
					<div class="col-tn-8 col-xs-9 col-md-8 archiveComponentControls">
						<div class="archiveComponentHeader">{$browseRelatedTitle}</div>
					</div>
				</a>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="browseRelatedModal{$browseRelatedId}" tabindex="-1" role="dialog" aria-labelledby="browseRelatedModalLabel{$browseRelatedId}" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close Window">&times;</button>
				<h2 class="modal-title h3" id="browseRelatedModalLabel{$browseRelatedId}">{$browseRelatedTitle}</h2>
			</div>
			<div class="modal-body">
				{foreach from=$browseRelatedItems item=related}
					<div class="row archive-field-row">
						<div class="result-value col-sm-12">
							<a href="{$related.url}">{$related.name|escape}</a> ({$related.count})
						</div>
					</div>
				{/foreach}
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
{/strip}
