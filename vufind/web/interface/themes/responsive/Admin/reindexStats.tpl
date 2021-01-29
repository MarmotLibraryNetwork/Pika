{strip}
	<div id="main-content" class="col-md-12">
		<h3>Indexing Statistics ({$indexingStatsDate})</h3>

		<form id="indexingDateSelection" name="indexingDateSelection" method="get" class="form form-inline">
			<div class="form-group">
				<label for="availableDates">Available Dates</label>
				<select id="availableDates" name="day" class="form-control">
					{foreach from=$availableDates item=date}
						<option value="{$date}">{$date}</option>
					{/foreach}
				</select>
			</div>
			<button type="submit" class="btn btn-default btn-sm">Set Date</button>

			<h4>Toggle columns:</h4>

			<div class="row">
				{foreach from=$indexingStatHeader item=itemHeader name=indexCols}
					{if $smarty.foreach.indexCols.index}{* Skip the first column for scope name *}
						<div class="col-sm-4">
							<button class="toggle-vis btn btn-default btn-primary" data-column="{$smarty.foreach.indexCols.index}"
							        style="width: 100%">{$itemHeader}</button>
						</div>
					{/if}
				{/foreach}
			</div>

		<div id="reindexingStatsContainer">
			{if $noStatsFound}
				<div class="alert-warning">Sorry, we couldn't find any stats.</div>
			{else}
				<table class="table table-condensed stripe order-column table-hover" id="reindexingStats">
					<thead>
					<tr>
						{foreach from=$indexingStatHeader item=itemHeader}
							<th>{$itemHeader}</th>
						{/foreach}
					</tr>
					</thead>
					<tbody>
					{foreach from=$indexingStats item=statsRow}
						<tr>
							{foreach from=$statsRow item=statCell name=statsLoop}
								<td>{$statCell}</td>
							{/foreach}
						</tr>
					{/foreach}
					</tbody>
				</table>
			{/if}
		</div>
	</div>
{/strip}
<script type="text/javascript">
	{literal}
	$(document).ready(function(){
/* Close side bar menu
		$('.menu-bar-option:nth-child(2)>a', '#vertical-menu-bar').filter(':visible').click();
*/

		var table = $('#reindexingStats').DataTable({
			"order": [[0, "asc"]],
			"paging": false
		});

		$('.toggle-vis').on( 'click', function (e) {
			e.preventDefault();

			// Get the column API object
			var column = table.column( $(this).attr('data-column') );

			// Toggle the visibility
			column.visible( ! column.visible() );

			// Toggle button class
			$(this).toggleClass("btn-primary");
		} )
						// Hide all but the first two columns initially
						.slice(2).click();

		$('#reindexingStats tbody').on( 'click', 'tr', function () {
			$(this).toggleClass('selected');
		} );
	})

	{/literal}
</script>