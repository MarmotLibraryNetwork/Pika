{strip}
<div class="archiveComponentContainer nopadding browseButtonBox">
	<div class="archiveComponent browseFilterContainer">
		<div class="archiveComponentBody">
			<div class="archiveComponentBox">
				<a href="#" data-bs-toggle="modal" data-bs-target="#browseSubjectsModal{$browseSubjectsId}">
					<div class="archiveComponentIconContainer">
						<img src="{$browseSubjectsImage}" width="100" height="100" alt=""{* "Alternative text of images should not be repeated as text" *} class="archiveComponentImage">
					</div>
					<div class="archiveComponentControls">
						<div class="archiveComponentHeader">{$browseSubjectsTitle|regex_replace:"/\bby\b/i":"by<br>"}</div>
					</div>
				</a>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="browseSubjectsModal{$browseSubjectsId}" tabindex="-1" role="dialog" aria-labelledby="browseSubjectsModalLabel{$browseSubjectsId}" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title h3" id="browseSubjectsModalLabel{$browseSubjectsId}">{$browseSubjectsTitle}</h2>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close Window"></button>
			</div>
			<div class="modal-body">
				{foreach from=$browseSubjectsItems item=subject}
					<div class="row archive-field-row">
						<div class="result-value col-md-12">
							<a href="{$subject.url}">{$subject.name|escape}</a> ({$subject.count})
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
