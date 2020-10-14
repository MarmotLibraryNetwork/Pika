<div>
	<div id="addToListComments" class="alert alert-info">
		<ul>
			<li>Enter one or more <strong>titles, ISBNs, barcodes, Archive PIDs, Grouped Work Ids, or Bib Ids</strong>.</li>
			<li>Each entry should be on its own line.</li>
			<li>We will search the catalog or archive for each entry and add the first match for each line to your list.</li>
			{if $itemCount > 1800}<li>Please note, there is a limit of 2000 items per list. Any items added that will exceed this limit will not be added.</li>{/if}
		</ul>
	</div>
	{if $itemCount < 2000}
	<form method="post" name="bulkAddToList" id="bulkAddToList" action="/MyAccount/MyList/{$listId}" class="form">
		<div>
			<input type="hidden" name="myListActionHead" value="bulkAddTitles">
			<textarea rows="5" cols="40" name="titlesToAdd" class="form-control"></textarea>
		</div>
	</form>
	{else}
		<div>
			<textarea rows="5" disabled="disabled" name="titlesToAdd" class="form-control">
				This list has reached or exceeded the maximum size of 2000 items
			</textarea>
		</div>
	{/if}

</div>