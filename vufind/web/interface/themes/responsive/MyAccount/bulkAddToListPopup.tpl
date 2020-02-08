<div>
	<div id="addToListComments" class="alert alert-info">
		<ul>
			<li>Enter one or more <strong>titles, ISBNs, barcodes, Archive PIDs, Grouped Work Ids, or Bib Ids</strong>.</li>
			<li>Each entry should be on its own line.</li>
			<li>We will search the catalog or archive for each entry and add the first match for each line to your list.</li>
		</ul>
	</div>
	<form method="post" name="bulkAddToList" id="bulkAddToList" action="/MyAccount/MyList/{$listId}" class="form">
		<div>
			<input type="hidden" name="myListActionHead" value="bulkAddTitles">
			<textarea rows="5" cols="40" name="titlesToAdd" class="form-control"></textarea>
		</div>
	</form>
</div>