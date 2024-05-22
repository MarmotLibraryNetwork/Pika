<h1 id="pageTitle" role="heading" aria-level="1" class="h2">{$shortPageTitle}</h1>
{if $lastError}
	<div class="alert alert-danger">
		{$lastError}
	</div>
{/if}
{if $instructions}
	<div class="alert alert-info">
		{$instructions}
	</div>
{/if}
{* Display Standard buttons at top of table as well as below *}
{if $canAddNew}
	<form action="" method="get" id="addNewFormTop">
		<div>
			<input type="hidden" name="objectAction" value="addNew">
			<button type="submit" value="addNew" class="btn btn-primary">Add New {$objectType}</button>
		</div>
	</form>
{/if}

{foreach from=$customListActions item=customAction}
	{if !is_null($customAction.action)}
		<form action="" method="get">
			<div>
				<input type="hidden" name="objectAction" value='{$customAction.action}'>
				<button type="submit" value='{$customAction.action}' class="btn btn-default">{$customAction.label}</button>
			</div>
		</form>
	{elseif is_null($customAction.action) && $customAction.onclick}
		<button class="btn btn-default{* btn-sm*}" onclick="{$customAction.onclick}">{$customAction.label}</button>
	{/if}
{/foreach}

<div class="adminTableRegion" id="adminTableRegion">
	<table class="adminTable table stripe order-column table-condensed" id="adminTable">
		<thead>
			<tr>
				{foreach from=$structure item=property key=id}
					{if !isset($property.hideInLists) || $property.hideInLists == false}
					<th title='{$property.description}'>{$property.label}</th>
{*					<th><label title='{$property.description}'>{$property.label}</label></th> // label tag is likely unneeded. WAVE plugin calls this an orphaned label since it's not associated with an input tag *}
					{/if}
				{/foreach}
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
			{if isset($dataList) && is_array($dataList)}
				{foreach from=$dataList item=dataItem key=id}
				<tr class='{cycle values="odd,even"} {$dataItem->class}'>

					{foreach from=$structure item=property}
						{assign var=propName value=$property.property}
						{assign var=propValue value=$dataItem->$propName}

						{if !isset($property.hideInLists) || $property.hideInLists == false}
							<td>
							{if $property.type == 'label'}
								{if strlen($propValue) > 0}{* Don't display link with no text *}
									<a href='/{$module}/{$toolName}?objectAction=edit&amp;id={$id}'>&nbsp;{$propValue}</a>
								{/if}
							{elseif $property.type == 'text' || $property.type == 'textarea' || $property.type == 'hidden'
							|| $property.type == 'file' || $property.type == 'integer' || $property.type == 'email'}
									{$propValue}
							{elseif $property.type == 'dateReadOnly'}
									{$propValue|date_format:"%F %T"} {* Use this format so that this column can sort numerically by the datetime *}
							{elseif $property.type == 'date'}
								{$propValue|date_format}
							{elseif $property.type == 'partialDate'}
								{assign var=propNameMonth value=$property.propNameMonth}
								{assign var=propMonthValue value=$dataItem->$propNameMonth}
								{assign var=propNameDay value=$property.propNameDay}
								{assign var=propDayValue value=$dataItem->$propDayValue}
								{assign var=propNameYear value=$property.propNameYear}
								{assign var=propYearValue value=$dataItem->$propNameYear}
								{if $propMonthValue}$propMonthValue{else}??{/if}/{if $propDayValue}$propDayValue{else}??{/if}/{if $propYearValue}$propYearValue{else}??{/if}
							{elseif $property.type == 'currency'}
								{assign var=propDisplayFormat value=$property.displayFormat}
								${$propValue|string_format:$propDisplayFormat}
							{elseif $property.type == 'enum'}
								{foreach from=$property.values item=propertyName key=propertyValue}
									{if $propValue == $propertyValue}{$propertyName}{/if}
								{/foreach}
							{elseif $property.type == 'multiSelect'}
								{if is_array($propValue) && count($propValue) > 0}
									{foreach from=$property.values item=propertyName key=propertyValue}
										{if in_array($propertyValue, array_keys($propValue))}{$propertyName}<br>{/if}
									{/foreach}
								{else}
									No values selected
								{/if}
							{elseif $property.type == 'oneToMany'}
								{if is_array($propValue) && count($propValue) > 0}
									{$propValue|@count}
								{else}
									Not set
								{/if}
							{elseif $property.type == 'checkbox'}
								{if ($propValue == 1)}Yes{else}No{/if}
							{elseif $property.type == 'image' || $property.type == 'readOnly'}
								{$propValue}
							{else}
								Unknown type to display {$property.type}
							{/if}
							</td>
						{/if}
					{/foreach}
						<td>
							<a href='/{$module}/{$toolName}?objectAction=edit&amp;id={$id}'>Edit</a>
							{if $additionalActions}
								{foreach from=$additionalActions item=action}
									<a href='{$action.path}&amp;id={$id}'>{$action.name}</a>
								{/foreach}
							{/if}
						</td>
				</tr>
				{/foreach}
		{/if}
		</tbody>
	</table>
</div>








{if $objectType == "Cover"}

	<script>
	var storagePath = "{$structure.cover.storagePath}";
	var processing = true;
	{literal}
	$("#adminTableRegion").addClass("drop").prepend("<h2 class='h4 text-center'>Drag covers here to upload</h2>");
	var coverDrop = new Dropzone("#adminTableRegion", {
		url: "/Admin/Covers?objectAction=addNew",
		clickable: false,
		paramName: "cover",
		acceptedFiles: "image/*",
		autoProcessQueue: false,
		addRemoveLinks: true,
		maxFiles: 50,
		parallelUploads: 50,
		maxFilesize: 1.8
	});

	coverDrop.on("error", function(e){
		if(e.accepted == false) {
			$(e.previewElement).css({"border":"solid red 2px", "background-color":"#FFC5C6", "text-align":"center"}).append("<strong style='color:darkred;'>file too large</strong>");
		}
		processing = false;

	});
	coverDrop.on("drop", function(e){
		$("#adminTable").hide();
		$(".btn-primary").hide();

		$("#addNewFormTop").append( $("<div class='btn btn-primary start' >Upload Covers</div>"));
		$("#addNewFormBottom").append( $("<div class='btn btn-primary start' >Upload Covers</div>"));

		$(".start").click(function(){
			coverDrop.processQueue();
		});
	});
	coverDrop.on("addedfile", function(file){

			var fileName = file.name;
			$.ajax({
				url: "/Admin/AJAX?&method=fileExists&fileName=" + fileName + "&storagePath=" + storagePath

			})
					.done (function(data)
					{
						if (data.exists == "true")
						{
							$(file.previewElement).css({"border":"solid red 2px", "background-color":"#FFC5C6", "text-align":"center"}).append("<strong style='color:darkred;'>file already exists</strong>");
						}
					})
		return false;
	});
	coverDrop.on("sending", function(file, xhr, formData){
		formData.append("objectAction", "save");
		formData.append("id","");

	});
	coverDrop.on("queuecomplete", function(){
		if(processing == true) {
			location.reload();
		}
	});
	coverDrop.on("reset", function(){
		$("#adminTable").show();
		$(".btn-primary").show();
		$(".start").remove();
	});


	{/literal}
	</script>
{/if}
{if $canAddNew}
	<form action="" method="get" id="addNewFormBottom">
		<div>
			<input type="hidden" name="objectAction" value="addNew">
			<button type="submit" value="addNew" class="btn btn-primary">Add New {$objectType}</button>
		</div>
	</form>
{/if}

{foreach from=$customListActions item=customAction}
	{if !is_null($customAction.action)}
		<form action="" method="get">
			<div>

				<input type="hidden" name="objectAction" value='{$customAction.action}'>

				<button type="submit" value='{$customAction.action}' class="btn btn-small btn-default">{$customAction.label}</button>

			</div>
		</form>
	{/if}

	{if is_null($customAction.action) && $customAction.onclick}
		<a class="btn btn-default btn-sm" onclick="{$customAction.onclick}">{$customAction.label}</a>
	{/if}
{/foreach}

{if isset($dataList) && is_array($dataList) && count($dataList) > 5}

	{if $objectType == "TranslationMap"}
		<script>
			{literal}
			$.fn.dataTable.ext.order['dom-numeric'] = function (settings, col){
				return this.api().column(col, {order:'index'}).nodes().map(function (td, i){
					return $('a', td).text().trim() * 1;
				});
			}
			$(document).ready(function(){
				$('.table').DataTable({

					columnDefs: [{orderable: true, targets: [1,2,3,4,5]}],
					pageLength: 100,
					"columnDefs": [{"orderDataType": "dom-numeric", "type": "numeric", "targets": 0}],
					initComplete: function(){

						this.api().columns([1,2,3,4]).every( function(){

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
											})
											.on('click', function(e){
													e.stopPropagation();
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

	{else}
	<script>
		{literal}
		$.fn.dataTable.ext.order['dom-numeric'] = function (settings, col){
			return this.api().column(col, {order:'index'}).nodes().map(function (td, i){
				return $('a', td).text().trim() * 1;
			});
		}
		$(document).ready(function(){
			$('#adminTable').DataTable({
				pageLength: 100,
				"columnDefs": [{"orderDataType": "dom-numeric", "type": "numeric", "targets": 0}],
					{/literal}
				{if $objectType == "MergedGroupedWork" || $objectType == "NonGroupedRecord"}
					{* TODO: CJ this sort column is actually mysql date time string. Initial glances this looks to be sorting okay
					 but there could be a better sorting method to pick out. - pascal *}
					{literal}
				"order": [[4, "desc"]]
					{/literal}
				{else}
					{literal}
				"order": [[0, "asc"]]
					{/literal}
				{/if}
					{literal}
			});
		});

		{/literal}
	</script>
		{/if}
{/if}