{strip}
	<div id="main-content" class="col-md-12">
		<form name="cleanupNovelistCache" method="post">
			<h3>Novelist Cache</h3>
			<div class="alert alert-info">There are currently <span class="badge">{$numCachedObjects}</span> objects in the cache.</div>
			<div class="form-group">
				<button type="submit" name="submit" class="btn btn-default">Clear Cache</button>
			</div>
		</form>
	</div>
{/strip}