{strip}
	<h2 id="pageTitle">{$shortPageTitle}</h2>
	<div class="adminTableRegion">
		<table class="adminTable table table-striped order-column table-condensed" id="adminTable">
			<thead>
			<tr>
{*				<th><label title="Content Type">Content Type</label></th>*}
				<th><label title="Content Type">Library</label></th>
				<th><label title="Num Objects">Num Objects</label></th>
				<th><label title="Disk Space">Disk Space Used</label></th>
				<th><label title="Disk Space (GB)">Disk Space Used (GB)</label></th>
			</tr>
			</thead>
			<tbody>
				{foreach from=$usageArray item=row}
					<tr>
						<td>{$row.displayName}</td>
						<td class="text-right">{$row.numObjects|number_format}</td>
						<td class="text-right">{$row.driveSpace|number_format}</td>
						<td class="text-right">{$row.driveSpaceDisplay|number_format:1}</td>
					</tr>
				{/foreach}
			</tbody>
			<tfoot>
				<tr>
					<td></td>
					<td class="text-right"><strong>{$totalObjects|number_format}</strong></td>
					<td class="text-right"><strong>{$totalBytes|number_format}</strong></td>
					<td class="text-right"><strong>{$totalDriveSpace|number_format:1} GB</strong></td>
				</tr>
			</tfoot>
		</table>
	</div>
{/strip}

{if isset($usageArray) && is_array($usageArray) && count($usageArray) > 5}
	<script>
		{literal}
		$(function(){
			$('#adminTable').DataTable({
				"order": [[0, "asc"]],
				paging: false
			});
		})
		{/literal}
	</script>
{/if}