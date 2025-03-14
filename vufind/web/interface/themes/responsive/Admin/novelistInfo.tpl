{strip}
	<div id="main-content" class="col-md-12">
		<form name="cleanupNovelistCache" method="post">
			<h1 role="heading" aria-level="1" class="h2">NoveList Information</h1>
			<div class="form-group">
				<div class="row">
					<div class="col-sm-4">
						<label for="checkId">Lookup Novelist Data: </label>
					</div>
					<div class="col-sm-4">
						<input type="text" id="checkId" placeholder="ISBN" name="checkISBN">
					</div>
					<div class="col-sm-4">
						<button type="submit" name="submit" class="btn btn-info">Lookup Novelist Data by ISBN</button>
					</div>
				</div>
			</div>
			{if $checkRecord}
				<div>ISBN to check: {$checkISBN}</div>
				<div id="novelistData"></div>
				<script>
					const novelistData = {$novelistData};
					{literal}
					$("#novelistData").empty().simpleJson({jsonObject: novelistData});
					{/literal}
				</script>
			{/if}
			<hr>
			<div class="alert alert-info">There are currently <span class="badge">{$numCachedObjects}</span> objects in the
				cache.
			</div>
			{if in_array('opacAdmin', $userRoles)}
				<div class="form-group">
					<button type="submit" name="truncateData" class="btn btn-default">Clear Cache</button>
				</div>
			{/if}
		</form>
	</div>
{/strip}