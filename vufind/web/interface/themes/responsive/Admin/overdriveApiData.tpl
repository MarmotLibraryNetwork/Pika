{strip}
	<div id="main-content" class="col-md-12">
		<h2>OverDrive API Data</h2>
		<div class="navbar row">
			<form class="form">
				<div class="form-group col-tn-12"  style="min-width: 40%">
					<label for="overDriveId" class="sr-only control-label">OverDrive Record Id:</label>
					<input id ="overDriveId" type="text" name="id" class="form-control" placeholder="OverDrive Record Id"{if !empty($smarty.get.id)}value="{$smarty.get.id}" {/if}>
				</div>
				<div class="form-group col-tn-12">
					<input class="btn btn-primary" type="submit" value="Product" name="formAction">
					<input class="btn btn-primary" type="submit" value="Metadata" name="formAction">
					<input class="btn btn-primary" type="submit" value="Availability" name="formAction">
				</div>
				<div class="form-group col-tn-12">
					<input class="btn btn-default" type="submit" value="Magazine Issues" name="formAction">
				</div>
				<div class="form-group col-tn-12">
					<input class="btn btn-default" type="submit" value="Search CrossRefId" name="formAction">
				</div>
			</form>
		</div>
		<button onclick="return Pika.OverDrive.forceUpdateFromAPI($('#overDriveId').val(), false)"
		        class="btn btn-sm btn-default">Mark to re-fetch update From API
		</button>
		<div class="row">
			<div class="col-tn-12">{$overDriveAPIData}</div>
		</div>
	</div>
{/strip}