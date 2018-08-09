{strip}
	<div id="main-content" class="col-md-12">
		<h2>OverDrive API Data</h2>
		<div class="navbar row">
			<form class="form-inline col-tn-12">
				<div class="form-group"  style="min-width: 40%">
					<label for="overDriveId" class="sr-only control-label">OverDrive Record Id:</label>
					<input id ="overDriveId" type="text" name="id" class="form-control" placeholder="OverDrive Record Id">
				</div>
				<button class="btn btn-primary" type="submit">Go</button>
			</form>
		</div>
		<div class="row">
			<div class="col-tn-12">{$overDriveAPIData}</div>
		</div>
	</div>
{/strip}