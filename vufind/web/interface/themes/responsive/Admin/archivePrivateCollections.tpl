{strip}
	<div id="main-content" class="col-md-12">
		<form name="archiveSubjects" method="post">
			<h1 role="heading" aria-level="1" class="h2">Archive Private Collections and Objects</h1>
			<div class="form-group">
				<label for="privateCollections">Collections that will be shown to the owning library only</label>
				<p class="help-block">List one Collection Node Id per line</p>
				<textarea name="privateCollections" id="privateCollections" class="form-control" rows="10">
					{$privateCollections}
				</textarea>
			</div>

			<div class="form-group">
				<label for="privateObjects">Objects that will be shown to the owning library only</label>
				<p class="help-block">List one Object Node Id per line</p>
				<textarea name="privateObjects" id="privateObjects" class="form-control" rows="10">{$privateObjects}</textarea>
			</div>

			<div class="form-group">
				<button type="submit" class="btn btn-primary">Save Changes</button>
			</div>

		</form>
	</div>
{/strip}