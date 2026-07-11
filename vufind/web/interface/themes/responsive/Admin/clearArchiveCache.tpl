{strip}
	<div id="main-content" class="col-lg-12">
		<form name="cleanupArchiveCache" method="post">
			<h1 role="heading" aria-level="1" class="h2">Archive Cache</h1>
			<div class="alert alert-info">There are currently <span class="badge">{$numCachedObjects}</span> objects in the cache.  Clearing the entire cache may result in performance issues until the cache is rebuilt.</div>

			<div class="mb-3">
				<button type="submit" name="submit" class="btn btn-outline-secondary">Clear Cache</button>
			</div>

		</form>
	</div>
{/strip}