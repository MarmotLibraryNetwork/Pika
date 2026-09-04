{strip}
<div class="archiveComponentContainer nopadding browseButtonBox">
	<div class="archiveComponent browseFilterContainer">
		<div class="archiveComponentBody">
			<div class="archiveComponentBox">
				<a href="#" data-bs-toggle="modal" data-bs-target="#browseRelatedModal{$browseRelatedId}">
					<div class="archiveComponentIconContainer">
						<img src="{$browseRelatedImage}" width="100" height="100" alt=""{* "Alternative text of images should not be repeated as text" *} class="archiveComponentImage">
					</div>
					<div class="archiveComponentControls">
						<div class="archiveComponentHeader">{$browseRelatedTitle|regex_replace:"/\bby\b/i":"by<br>"}</div>
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
				<h2 class="modal-title h3" id="browseRelatedModalLabel{$browseRelatedId}">{$browseRelatedTitle}</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close Window"></button>
			</div>
			<div class="modal-body">
				{foreach from=$browseRelatedItems item=related}
					<div class="row archive-field-row">
						<div class="result-value col-md-12">
							<a href="{$related.url}">{$related.name|escape}</a> ({$related.count})
						</div>
					</div>
				{/foreach}
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
{/strip}
