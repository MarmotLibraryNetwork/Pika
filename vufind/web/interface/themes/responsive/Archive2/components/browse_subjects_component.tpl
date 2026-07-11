{strip}
<div class="archiveComponentContainer nopadding col-md-12 col-lg-6">
	<div class="archiveComponent browseFilterContainer">
		<div class="row archiveComponentBody">
			<div class="archiveComponentBox">
				<a href="#" data-bs-toggle="modal" data-bs-target="#browseSubjectsModal{$browseSubjectsId}">
					<div class="col-4 col-sm-3 col-lg-4 archiveComponentIconContainer">
						<img src="{$browseSubjectsImage}" width="100" height="100" alt="{$browseSubjectsTitle|escape}" class="archiveComponentImage">
					</div>
					<div class="col-8 col-sm-9 col-lg-8 archiveComponentControls">
						<div class="archiveComponentHeader">{$browseSubjectsTitle}</div>
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
