{strip}
	<div id="main-content" class="col-md-12">
		<h1 role="heading" aria-level="1" class="h2">Indexing Statistics : {$indexingStatsDate}
			{if !empty($compareTo)} vs {$compareTo}{/if}
		</h1>

		<div class="row">

		<form id="indexingDateSelection" name="indexingDateSelection" method="get" class="form form-inline">
			<div class="col-sm-6">
			<div class="form-group">
				<label for="availableDates">Available Dates</label>
				<select id="availableDates" name="day" class="form-control" onchange="$('#indexingDateSelection').submit()">
					{foreach from=$availableDates item=date}
						<option value="{$date}"{if $date == $indexingStatsDate} selected="selected"{/if}>{$date}</option>
					{/foreach}
				</select>
			</div>
{*			<button type="submit" class="btn btn-default btn-sm">Set Date</button>*}
			</div>
			<div class="col-sm-6">
			<div class="form-group">
				<label for="compareTo">Compare To</label>
				<select id="compareTo" name="compareTo" class="form-control" onchange="$('#indexingDateSelection').submit()">
					<option></option>
					{foreach from=$availableDates item=date}
						<option value="{$date}"{if $date == $indexingStatsDate} disabled="disabled"}{elseif $date == $compareTo} selected="selected"{/if}>{$date}</option>
					{/foreach}
				</select>
			</div>
{*			<button type="submit" class="btn btn-default btn-sm">Compare</button>*}
			</div>
		</form>
		</div>

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
				{if !empty($compareTo)}
					<br>
					<table class="table">
						<tr>
							<th class="success">Increased since {$pastDate}</th>
							<th class="danger">Decreased since {$pastDate}</th>
						</tr>
					</table>
					<br>
				{/if}
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
								<td{if !empty($compareTo)}{if $statCell > 0} class="success"{elseif $statCell < 0} class="danger"{/if}{/if}>{$statCell}</td>
							{/foreach}
						</tr>
					{/foreach}
					</tbody>
				</table>
			{/if}
		</div>
	</div>
{/strip}
<script>
	{literal}
	$(function(){
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
		{/literal}
			{if !empty($showTheseColumns)}

		.filter(
						function(index){ldelim}
{*
							return ![
								{foreach from=$showTheseColumns name=displayColumns item=showColumn}{if $showColumn}{$smarty.foreach.displayColumns.index - 2}, {/if}{/foreach}
							].includes(index);
*}

							return ![
								{foreach from=$showTheseColumns item=showColumn}{$showColumn}, {/foreach}
							].includes(index);

						{rdelim}).click();
			{else}
/*
							// Hide all but the first two columns initially
*/
							.slice(2).click();

		{/if}
		{literal}

		$('#reindexingStats tbody').on( 'click', 'tr', function () {
			$(this).toggleClass('selected');
		} );
	})

	{/literal}
</script>