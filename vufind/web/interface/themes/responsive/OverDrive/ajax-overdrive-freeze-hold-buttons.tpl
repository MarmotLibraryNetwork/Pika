{strip}
	<button onclick="return Pika.OverDrive.cancelOverDriveHold('{$patronId}', '{$overDriveId}');" class="btn btn-warning">Cancel Hold</button>
	<button onclick="return Pika.OverDrive.thawOverDriveHold('{$patronId}', '{$overDriveId}');" class="btn btn-default">{translate text="Thaw Hold"}</button>
	<input class="btn btn-primary" type="submit" name="submit" value="{translate text="Update Hold"}" onclick="$('#overdriveFreezeHoldPromptsForm').submit(); return false;">
{/strip}