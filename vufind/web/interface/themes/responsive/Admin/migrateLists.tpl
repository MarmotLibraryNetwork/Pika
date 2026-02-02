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
				<div class="alert alert-success">{$migratedLists} lists were migrated successfully</div>
				{if $errorBarcodes > 0}
					<div class="alert alert-warning">The following lists were not successful:
						<ul>
							{foreach from=$errorBarcodes item=errorBarcode}
								<li>{$errorBarcode}</li>
							{/foreach}
						</ul>
					</div>
				{/if}
			{/if}
		{/if}

		<form name="ListMigrationFile" method="post" enctype="multipart/form-data" class="form-horizontal">
			<fieldset>

				<input type="hidden" name="objectAction" value="processListsFile">
				<div class="row form-group">
					<label for="file" class="col-sm-5 control-label">List CSV File (one list per line): </label>
					<div class="col-sm-7">
						<input type="file" name="listsFile" id="listsFile" accept=".csv,.txt" class="form-control">
					</div>
				</div>
				<div class="form-group">
					<div class="controls">
						<input type="submit" name="submit" value="Process Lists File" class="btn btn-primary">
					</div>
				</div>
			</fieldset>
		</form>
	</div>
{/strip}