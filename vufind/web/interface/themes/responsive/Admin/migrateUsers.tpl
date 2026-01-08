{strip}
	<div id="main-content" class="col-tn-12 col-xs-12">
		<h1 role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
		{if $instructions}
			<div class="alert alert-info">{$instructions}</div>
		{/if}

		{if $error}
			<div class="alert alert-danger">{$error}</div>
		{else}
			{if $submit}
				<div class="alert alert-success">{$migratedUsers} users were migrated successfully</div>
			{/if}
		{/if}

		<form name="UserMigrationFile" method="post" enctype="multipart/form-data" class="form-horizontal">
			<fieldset>

				<input type="hidden" name="objectAction" value="processFile">
				<div class="row form-group">
					<label for="file" class="col-sm-5 control-label">Barcode Text File (one barcode per line): </label>
					<div class="col-sm-7">
						<input type="file" name="migrationBarcodes" id="migrationBarcodes" accept=".csv,.txt" class="form-control">
					</div>
				</div>
				<div class="form-group">
					<div class="controls">
						<input type="submit" name="submit" value="Process Migration File" class="btn btn-primary">
					</div>
				</div>
			</fieldset>
		</form>
	</div>
{/strip}