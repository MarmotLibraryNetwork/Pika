{strip}
    {strip}
			<div id="main-content" class="col-tn-12 col-xs-12">
				<form name="addAdministrator" method="post" enctype="multipart/form-data" class="form-horizontal">
					<fieldset>
						<legend><h1>User Administration</h1></legend>

              {if $error}
								<div class="alert alert-danger">{$error}</div>
              {/if}
              {if $success}
								<div class="alert alert-success">{$success}</div>
              {/if}

						<input type="hidden" name="userAction" value="resetDisplayName">
						<div class="row form-group">
							<label for="barcode" class="col-sm-2 control-label">Barcode: </label>
							<div class="col-sm-6">
								<input type="text" name="barcode" id="barcode" class="form-control"{if $barcode} value="{$barcode}"{/if}>
							</div>
							<div class="col-sm-2">
								<button type="submit" class="btn btn-primary">Reset User's display name</button>
							</div>
						</div>
						<div class="form-group">
						</div>
					</fieldset>
				</form>
			</div>
    {/strip}
{/strip}