{strip}
	{if $listError}<p class="alert alert-danger">{$listError|translate}</p>{/if}
	<form name="listForm" class="form form-horizontal" id="addListForm">
		<div class="form-group">
			<label for="listTitle" class="col-sm-3 control-label">{translate text="List"}:</label>
			<div class="col-sm-9">
				<input type="text" id="listTitle" name="title" value="{$list->title|escape:"html"}" size="50" class="form-control">
			</div>
		</div>
		<div class="form-group">
		  <label for="listDesc" class="col-sm-3 control-label">{translate text="Description"}:</label>
			<div class="col-sm-9">
		    <textarea name="desc" id="listDesc" rows="3" cols="50" class="form-control">{$list->desc|escape:"html"}</textarea>
			</div>
		</div>
		<div class="form-group">
			<label for="public" class="col-sm-3 control-label">{translate text="Allow Public Access"}:</label>
			<div class="col-sm-9">
				<input type="checkbox" name="public" id="public" data-switch="">
			</div>
		</div>
	  <input type="hidden" name="groupedWorkId" value="{$groupedWorkId}">
	</form>
	<br>
{/strip}
<script type="text/javascript">{literal}
	$(document).ready(Pika.setupCheckBoxSwitches);
{/literal}</script>