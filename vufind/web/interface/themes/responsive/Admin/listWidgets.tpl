{strip}
	<div id="main-content" class="col-md-12">
		<h3>Available List Widgets</h3>
		<div class="alert alert-info">
			For more information on how to create List Widgets, please see the <a href="https://docs.google.com/document/d/1nftHL4yrUWjWeuW7qSUz3L9XHR0vs8pTU4E-Aa6LPpk">online documentation</a>
		</div>
		<div id="widgets"></div>
		{* Select a widget to edit *}
		<div id="availableWidgets">
			{if $canAddNew}
				<input type="button" class="btn btn-primary" name="addWidget" value="Add Widget" onclick="window.location = '/Admin/ListWidgets?objectAction=add';">
			{/if}
			<table class="table table-striped">
		<thead><tr><th>Id</th><th>Name</th><th>Library</th><th>Description</th><th class="sorter-false filter-false ">Actions</th></tr></thead>
		<tbody>
			{foreach from=$availableWidgets key=id item=widget}
				<tr><td>{$widget->id}</td><td>{$widget->name}</td><td>{$widget->getLibraryName()}</td><td>{$widget->description}</td><td>
					<div class="btn-group-vertical btn-group-sm">
						<a class="btn btn-sm btn-default" href="/Admin/ListWidgets?objectAction=view&id={$widget->id}" role="button">View</a>
						<a class="btn btn-sm btn-default" href="/Admin/ListWidgets?objectAction=edit&id={$widget->id}" role="button">Edit</a>
						<a class="btn btn-sm btn-default" href="/API/SearchAPI?method=getListWidget&id={$widget->id}" role="button">Preview</a>
						{if $canDelete}
							<a class="btn btn-sm btn-danger" href="/Admin/ListWidgets?objectAction=delete&id={$widget->id}" role="button" onclick="return confirm('Are you sure you want to delete {$widget->name}?');">Delete</a>
						{/if}
					</div>
				</td>
			{/foreach}
		</tbody>
		</table>
		{if $canAddNew}
			<input type="button" class="btn btn-primary" name="addWidget" value="Add Widget" onclick="window.location = '/Admin/ListWidgets?objectAction=add';">
		{/if}
		</div>
	</div>
	{if !empty($availableWidgets) && count($availableWidgets) > 5}
		<script type="text/javascript">
			{literal}
			$("#availableWidgets>table").tablesorter({cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader', widgets:['zebra', 'filter'] });
			{/literal}
		</script>
	{/if}
{/strip}