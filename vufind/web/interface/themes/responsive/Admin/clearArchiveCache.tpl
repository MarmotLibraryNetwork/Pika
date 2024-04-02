{strip}
	<div id="main-content" class="col-md-12">
		<form name="cleanupArchiveCache" method="post">
			<h1 role="heading" class="h2">Archive Cache</h1>
			<div class="alert alert-info">There are currently <span class="badge">{$numCachedObjects}</span> objects in the cache.  Clearing the entire cache may result in performance issues until the cache is rebuilt.</div>

			<div class="form-group">
				<button type="submit" name="submit" class="btn btn-default">Clear Cache</button>
			</div>

		</form>
	</div>
{/strip}