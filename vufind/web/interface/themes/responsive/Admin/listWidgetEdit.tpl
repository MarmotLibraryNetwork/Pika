{css filename="listWidget.css"}
{strip}
	<div id="main-content">
		<div class="alert alert-info">
			For more information on how to create List Widgets, please see the <a href="https://marmot-support.atlassian.net/l/c/CByg62XD">online documentation</a>
		</div>
		<h1>Edit List Widget</h1>
		<div class="btn-group">
			<a class="btn btn-sm btn-default" href="/Admin/ListWidgets">All Widgets</a>
			<a class="btn btn-sm btn-default" href="/Admin/ListWidgets?objectAction=view&id={$object->id}">View</a>
			<a class="btn btn-sm btn-default" href="/API/SearchAPI?method=getListWidget&id={$object->id}">Preview</a>
			{if $canDelete}
				<a class="btn btn-sm btn-danger" href="/Admin/ListWidgets?objectAction=delete&id={$object->id}" onclick="return confirm('Are you sure you want to delete {$object->name}?');">Delete</a>
			{/if}
		</div>

		{$editForm}
	</div>
{/strip}
{if $edit}
<script>{literal}
	$(function(){
		$('#selectedWidgetLists tbody').sortable({
			update: function(event, ui){
				var listOrder = $(this).sortable('toArray').toString();
				alert("ListOrder = " + listOrder);
			}
		});
		$('#selectedWidgetLists tbody').disableSelection();
	});{/literal}
</script>
{/if}