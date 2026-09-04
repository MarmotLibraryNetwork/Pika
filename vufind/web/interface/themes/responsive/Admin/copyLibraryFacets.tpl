	<div id="main-content">
		<h1>Copy Library {$facetType|capitalize} Facets</h1>
		{if count($allLibraries) == 0}
			<div class="alert alert-warning">Sorry, there are no libraries available for you to copy {$facetType} facets from.</div>
		{else}
			<form action="/Admin/Libraries" method="get">
				<div class="d-flex flex-wrap align-items-center gap-2">
					<input type="hidden" name="id" value="{$id}">
					<input type="hidden" name="objectAction" value="{$objectAction}">
					<label for="libraryToCopyFrom" class="col-form-label">Select a library to copy {$facetType} facets from:</label>
					<select id="libraryToCopyFrom" name="libraryToCopyFrom" class="form-select w-auto">
						{foreach from=$allLibraries item=library}
							<option value="{$library->libraryId}">{$library->displayName}</option>
						{/foreach}
					</select>
					<input type="submit" name="submit" value="Copy Facets" class="btn btn-primary">
				</div>
			</form>
		{/if}
	</div>
