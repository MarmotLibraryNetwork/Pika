
<h2 id="pageTitle">{$shortPageTitle}</h2>
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

			<button type="submit" value='{$customAction.action}' class="btn btn-small btn-default">{$customAction.label}</button>

		</div>
	</form>
	{/if}

	{if is_null($customAction.action) && $customAction.onclick}
		<a class="btn btn-default btn-sm" onclick="{$customAction.onclick}">{$customAction.label}</a>
	{/if}
{/foreach}

<div class="adminTableRegion" id="adminTableRegion">
	<table class="adminTable table table-striped table-condensed smallText" id="adminTable">
		<thead>
			<tr>
				{foreach from=$structure item=property key=id}
					{if !isset($property.hideInLists) || $property.hideInLists == false}
					<th><label title='{$property.description}'>{$property.label}</label></th>
					{/if}
				{/foreach}
				<th class="sorter-false filter-false">Actions</th>
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
								{if $dataItem->class != 'objectDeleted'}
									<a href='/{$module}/{$toolName}?objectAction=edit&amp;id={$id}'>&nbsp;</span>{$propValue}</a>
								{/if}
							{elseif $property.type == 'text' || $property.type == 'textarea' || $property.type == 'hidden' || $property.type == 'file' || $property.type == 'integer' || $property.type == 'email'}
								{$propValue}
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
										{if in_array($propertyValue, array_keys($propValue))}{$propertyName}<br/>{/if}
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
							{elseif $property.type == 'image'}
								{$propValue}
							{else}
								Unknown type to display {$property.type}
							{/if}
							</td>
						{/if}
					{/foreach}
					{if $dataItem->class != 'objectDeleted'}
						<td>
							<a href='/{$module}/{$toolName}?objectAction=edit&amp;id={$id}'>Edit</a>
							{if $additionalActions}
								{foreach from=$additionalActions item=action}
									<a href='{$action.path}&amp;id={$id}'>{$action.name}</a>
								{/foreach}
							{/if}
						</td>
					{/if}
				</tr>
				{/foreach}
		{/if}
		</tbody>
	</table>
</div>








{if $objectType == "Cover"}

	<script>
	var storagePath = "{$structure.cover.storagePath}";
	{literal}
	$("#adminTableRegion").addClass("drop").prepend("<h4>Drag covers here to upload</h4>");
	var coverDrop = new Dropzone("#adminTableRegion", {
		url: "/Admin/Covers?objectAction=addNew",
		clickable: false,
		paramName: "cover",
		acceptedFiles: "image/*",
		autoProcessQueue: false,
		addRemoveLinks: true,
		maxFiles: 50,
		parallelUploads: 50
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
		location.reload();
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
	<form action="" method="get">
		<div>
			<input type="hidden" name="objectAction" value='{$customAction.action}'>
			<button type="submit" value='{$customAction.action}' class="btn btn-small btn-default">{$customAction.label}</button>
		</div>
	</form>
{/foreach}

{if isset($dataList) && is_array($dataList) && count($dataList) > 5}
<script type="text/javascript">
	{literal}
	$("#adminTable").tablesorter({cssAsc: 'sortAscHeader', cssDesc: 'sortDescHeader', cssHeader: 'unsortedHeader',
		widgets:['zebra', 'filter'] });
	{/literal}
</script>

	<script type="text/javascript">
		{literal}
		$(document).ready(function(){
			$('#adminTable').DataTable();
		})

		{/literal}
	</script>
{/if}