{strip}
	<div class="modal-header">
		<h2 class="modal-title h3" id="myModalLabel">{$form.title}</h2>{* Sematically subheading of main page's h1 (for accessibility *}
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close Window"></button>
	</div>
	<div class="modal-body">
			{$form.modalBody}
	</div>
	<div class="modal-footer">
		<button class="btn" data-bs-dismiss="modal" id="modalClose">Close</button>
		<span class="modal-buttons">
			{$form.modalButtons}
	</span>
	</div>
{/strip}
