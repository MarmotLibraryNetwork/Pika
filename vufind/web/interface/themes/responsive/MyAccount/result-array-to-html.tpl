{strip}
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close Window">&times;</button>
		<h2 class="modal-title h4" id="myModalLabel">{$form.title}</h2>{* Sematically subheading of main page's h1 (for accessibility *}
	</div>
	<div class="modal-body">
			{$form.modalBody}
	</div>
	<div class="modal-footer">
		<button class="btn" data-dismiss="modal" id="modalClose">Close</button>
		<span class="modal-buttons">
			{$form.modalButtons}
	</span>
	</div>
{/strip}
