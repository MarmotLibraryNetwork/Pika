{strip}
	{*TODO: This might be an obsolete template *}
	<div class="modal-header">
		<h2 class="modal-title h4" id="modal-title">Related Records</h2>{* Sematically subheading of main page's h1 (for accessibility *}
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close Window"></button>
	</div>
	<div class="modal-body">
		{include file="GroupedWork/relatedRecords.tpl"}
	</div>
	<div class="modal-footer">
		<button class="btn" data-bs-dismiss="modal" id="modalClose">Close</button>
	</div>
{/strip}