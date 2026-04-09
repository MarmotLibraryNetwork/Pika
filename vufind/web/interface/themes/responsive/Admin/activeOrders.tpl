{strip}
<div id="main-content" class="col-md-12">
	<h1 role="heading" aria-level="1" class="h2">Active Orders</h1>
	{if $notSierra}
		<div class="alert alert-info">Active Orders is only available for Sierra ILS systems.</div>
	{elseif $noFile}
		<div class="alert alert-warning">No active_orders.csv found in any indexing profile's MARC path.</div>
	{else}
		{if $profiles|@count > 1}
			<form method="get" action="/Admin/ActiveOrders" class="form-inline" style="margin-bottom:1em;">
				<label for="profileSelect">Indexing Profile: </label>
				<select id="profileSelect" name="id" class="form-control" onchange="this.form.submit()">
					{foreach from=$profiles key=pid item=pname}
						<option value="{$pid}"{if $pid == $selectedId} selected="selected"{/if}>{$pname|escape}</option>
					{/foreach}
				</select>
			</form>
		{/if}
		<p class="text-muted">File last updated: {$fileDate|date_format:"%F %T"}</p>
		{if $rows}
			<div class="table-responsive">
				<table class="table table-striped table-bordered table-condensed" id="activeOrdersTable">
					<thead>
						<tr>
							{foreach from=$headers item=header}
								<th>{$header|escape}</th>
							{/foreach}
						</tr>
					</thead>
					<tbody>
						{foreach from=$rows item=row}
							<tr>
								{foreach from=$row item=cell}
									<td>{$cell|escape}</td>
								{/foreach}
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		{else}
			<div class="alert alert-info">The active_orders.csv file is empty.</div>
		{/if}
	{/if}
</div>
{/strip}
<script>
	{literal}
	$(function() {
		$('#activeOrdersTable').DataTable({
			"order": [[0, "asc"]],
			pageLength: 100,
			initComplete: function() {
				var filterColumns = ['order_status_code', 'location_code', 'accounting_unit_code_num'];
				this.api().columns().every(function() {
					var column = this;
					var headerText = $(column.header()).text().trim();
					if (filterColumns.indexOf(headerText) !== -1) {
						var select = $('<select><option value=""></option></select>')
							.appendTo($(column.header()))
							.on('change', function() {
								var val = $.fn.dataTable.util.escapeRegex($(this).val());
								column.search(val ? '^' + val + '$' : '', true, false).draw();
							})
							.on('click', function(e) { e.stopPropagation(); });
						column.data().unique().sort().each(function(d) {
							select.append('<option value="' + d + '">' + d + '</option>');
						});
					}
				});
			}
		});
	})
	{/literal}
</script>
