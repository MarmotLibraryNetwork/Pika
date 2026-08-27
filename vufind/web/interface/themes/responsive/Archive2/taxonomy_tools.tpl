{strip}
	{* Save this taxonomy term to a user list - D-5469. Stored in tax_vocabulary-colon-tid form;
	   $termListEntryDomId is assigned by Archive2/TaxonomyObject.php. *}
	{* NOTE: the save button is the only tool here at the moment, so the showFavorites test wraps
	   the whole container rather than just the button. That keeps an empty download-options div
	   off the page for libraries with user lists turned off. If a second tool is added here,
	   move the test back around the button alone and let the container render on its own. *}
	{if $showFavorites == 1}
		<div id="download-options">{* Use the same button container as archive objects to get common styling *}
			<div class="taxonomy-tools">
				<button onclick="return Pika.Archive2.showSaveToListForm(this, '{$termListEntryDomId|escape}');" class="btn btn-default">{translate text='Add to favorites'}</button>
			</div>
		</div>
	{/if}
{/strip}
