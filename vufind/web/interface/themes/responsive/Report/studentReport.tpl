{strip}
	<div id="main-content" class="col-md-12">
		{if $loggedIn}
			<h1>Student Report</h1>
			<div class="alert alert-info">
				For more information on using student reports, see the <a href="https://docs.google.com/document/d/1ASo7wHL0ADxG8Q8oIRTeXybja7QJq7mW-77e3C1X7f8">online documentation</a>.
			</div>
			{foreach from=$errors item=error}
				<div class="error">{$error}</div>
			{/foreach}
			<form class="form form-inline">
				<label for="selectedReport" class="control-label">Available Reports&nbsp;</label>
				<select name="selectedReport" id="selectedReport" class="form-control input-sm">
					{foreach from=$availableReports item=curReport key=reportLocation}
						<option value="{$reportLocation}" {if $curReport==$selectedReport}selected="selected"{/if}>{$curReport}</option>
					{/foreach}
				</select>
				&nbsp;
				<label for="showOverdueOnly" class="control-label">Include&nbsp;</label>
				<select name="showOverdueOnly" id="showOverdueOnly" class="form-control input-sm">
					<option value="overdue" {if $showOverdueOnly}selected="selected"{/if}>Overdue Items</option>
					<option value="checkedOut" {if !$showOverdueOnly}selected="selected"{/if}>Checked Out Items</option>
				</select>
				&nbsp;
				<input type="submit" name="showData" value="Show Data" class="btn btn-sm btn-primary"/>
				&nbsp;
				<input type="submit" name="download" value="Download CSV" class="btn btn-sm btn-info"/>
			</form>

			{if $reportData}
				<br>
				<p>
					{assign var=reportCount value=$reportData|@count}
					There are a total of <strong>{$reportCount-1}</strong> rows that meet your criteria.
				</p>
				<table id="studentReportTable" class="table table-condensed stripe">
					{foreach from=$reportData item=dataRow name=studentData}
						{if $smarty.foreach.studentData.index == 0}
							<thead>
								<tr>
									{foreach from=$dataRow item=dataCell name=dataCol}

											<th>{$dataCell}</th>

									{/foreach}
								</tr>

							</thead>

						{else}
							{if $smarty.foreach.studentData.index == 1}
								<tbody>
							{/if}
							<tr>
								{foreach from=$dataRow item=dataCell}

									<td>{$dataCell}</td>

								{/foreach}
							</tr>
						{/if}
					{/foreach}
					</tbody>
				</table>

			<script type="text/javascript">
				{literal}
				$(document).ready(function(){
					$('.table').DataTable({

						columnDefs: [{orderable: false, targets: [0,1,3,5,6,11,13]}],
						pageLength: 100,
						initComplete: function(){

							this.api().columns([0,1,3,5,6,11,13]).every( function(){

								var column = this;

								var select= $('<select><option value =""></option></select>')
												.appendTo($(column.header()))
												.on('change', function(){
													var val =$.fn.dataTable.util.escapeRegex(
																	$(this).val()
													);
													column
													  .search( val ? '^'+val+'$' : '', true, false)
													.draw();
												});
								column.data().unique().sort().each(function (d,j) {
									select.append('<option value"' +d+'">'+d+'</option>')
								});
							});
						}
					});
				});

				{/literal}
			</script>
			{/if}
		{else}
        {include file="MyAccount/loginRequired.tpl"}
		{/if}
	</div>
{/strip}