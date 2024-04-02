{strip}
	<div id="main-content" class="col-md-12">
		<form name="archiveSubjects" method="post">
			<h1 role="heading" class="h2">Archive Private Collections</h1>
			<div class="form-group"><label for="privateCollections">Collections that will be shown to the owning library only</label>
				<p class="help-block">List one PID per line</p>
				<textarea name="privateCollections" id="privateCollections" class="form-control" rows="10">
					{$privateCollections}
				</textarea>

			</div>

			<div class="form-group">
				<button type="submit" class="btn btn-primary">Save Changes</button>
			</div>

		</form>
	</div>
{/strip}