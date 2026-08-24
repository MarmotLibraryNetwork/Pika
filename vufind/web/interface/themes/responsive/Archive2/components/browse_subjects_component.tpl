{strip}
<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div class="archiveComponent browseFilterContainer">
		<div class="row archiveComponentBody">
			<div class="archiveComponentBox">
				<a href="#" data-toggle="modal" data-target="#browseSubjectsModal{$browseSubjectsId}">
					<div class="col-tn-4 col-xs-3 col-md-4 archiveComponentIconContainer">
						<img src="{$browseSubjectsImage}" width="100" height="100" alt=""{* "Alternative text of images should not be repeated as text" *} class="archiveComponentImage">
					</div>
					<div class="col-tn-8 col-xs-9 col-md-8 archiveComponentControls">
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
				<button type="button" class="close" data-dismiss="modal" aria-label="Close Window">&times;</button>
				<h2 class="modal-title h3" id="browseSubjectsModalLabel{$browseSubjectsId}">{$browseSubjectsTitle}</h2>
			</div>
			<div class="modal-body">
				{foreach from=$browseSubjectsItems item=subject}
					<div class="row archive-field-row">
						<div class="result-value col-sm-12">
							<a href="{$subject.url}">{$subject.name|escape}</a> ({$subject.count})
						</div>
					</div>
				{/foreach}
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
{/strip}
