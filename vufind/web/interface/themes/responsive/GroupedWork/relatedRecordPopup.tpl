{strip}
	{*TODO: This might be an obsolete template *}
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close Window">&times;</button>
		<h2 class="modal-title h4" id="modal-title">Related Records</h2>{* Sematically subheading of main page's h1 (for accessibility *}
	</div>
	<div class="modal-body">
		{include file="GroupedWork/relatedRecords.tpl"}
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" id="modalClose">Close</button>
	</div>
{/strip}