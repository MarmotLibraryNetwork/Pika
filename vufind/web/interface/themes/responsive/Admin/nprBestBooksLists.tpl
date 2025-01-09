{strip}
	<h1 role="heading" aria-level="1" class="h2">Pika Lists based on {$listsTitle}</h1>
	<div class="alert alert-info">
		For more information on using the {$listsTitle} lists, see the <a href="https://marmot-support.atlassian.net/l/cp/cQdAwr4B">online documentation</a>.
	</div>


	<h2 class="h3">Create or Update a List</h2>

	{if $error}
		<div class="alert alert-danger">{$error}</div>
	{/if}

	{if $successMessage}
		<div class="alert alert-success">{$successMessage}</div>
	{/if}

	<form action="" method="post" id="buildList">
		<div class="form-group">
			<label for="selectedList">Pick a {$listsTitle} list to build a Pika list for: </label>
			<div class="alert alert-warning">Current year is an included option but may not have a list yet.</div>
			<!-- Give the user a list of all available lists from NYT -->
			<select name="selectedList" id="selectedList" class="form-control">
				{foreach from=$availableLists item="year"}
					<option value="{$year}" {if $selectedListName == $year}selected="selected"{/if}>{$year}</option>
				{/foreach}
			</select>
		</div>
		{*<input type="hidden" name="existingListId" id="existingListId" value="">*}
		<button type="submit" name="submit" class="btn btn-primary">Create/Update List</button>
	</form>

	{if !empty($pikaLists)}
		<h2 class="h3">Existing {$listsTitle} Lists</h2>
		<table class="table table-bordered table-hover">
			<tr>
				<th>
					Name
				</th>
				<th>Last Updated</th>
			</tr>
			{foreach from=$pikaLists item="pikaList"}
				<tr>
					<td>
						<a href="/MyAccount/MyList/{$pikaList->id}">{$pikaList->title} ({$pikaList->numValidListItems()})</a>
					</td>
					<td>{$pikaList->dateUpdated|date_format}</td>
				</tr>
			{/foreach}
		</table>
	{/if}
{/strip}