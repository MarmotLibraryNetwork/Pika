{strip}
	<div id="main-content" class="col-lg-12">
		<h1 role="heading" aria-level="1" class="h2">{$pageTitleShort}</h1>
		<hr>

		{if $alert}{$alert}{/if}

		<div class="h4">Filter by</div>
		<form class="navbar row">
        {if !empty($filterLabel)}
					<div class="mb-3 col-sm-7 d-flex flex-wrap align-items-center gap-2">
						<label for="filterCount" class="col-form-label">{$filterLabel}:</label>
						<input style="width: 125px;" id="filterCount" name="filterCount" type="number" min="0" class="form-control w-auto" {if !empty($smarty.request.filterCount)} value="{$smarty.request.filterCount}"{/if}>
						<button class="btn btn-primary" type="submit">Go</button>
					</div>
        {/if}
			<div class="mb-3 col-sm-5">
				<span class="float-end d-flex align-items-center gap-2">
					<label for="pagesize" class="col-form-label">Entries Per Page:</label>
					<select id="pagesize" name="pagesize" class="pagesize form-select w-auto input-sm">
						<option value="30"{if $recordsPerPage == 30} selected="selected"{/if}>30</option>
						<option value="50"{if $recordsPerPage == 50} selected="selected"{/if}>50</option>
						<option value="75"{if $recordsPerPage == 75} selected="selected"{/if}>75</option>
						<option value="100"{if $recordsPerPage == 100} selected="selected"{/if}>100</option>
					</select>
				</span>
			</div>
		</form>

{*
		<form class="navbar form-inline row">
			{if !empty($filterLabel)}
				<div class="mb-3 col-sm-7">
					<div class="input-group">
					<label for="filterCount" class="form-label input-group-text">{$filterLabel}</label>
					<input id="filterCount" name="filterCount" type="number" min="0" class="form-control" {if !empty($smarty.request.filterCount)} value="{$smarty.request.filterCount}"{/if}>
					<button class="btn btn-primary" type="submit">Go</button>
				</div>
				</div>
      {/if}
			<div class="mb-3 col-sm-5 float-end">
				<span class="float-end">
					<div class="input-group">
					<label for="pagesize" class="form-label input-group-text">Entries Per Page</label>
					<select id="pagesize" name="pagesize" class="pagesize form-select input-sm" onchange="Pika.changePageSize()">
						<option value="30"{if $recordsPerPage == 30} selected="selected"{/if}>30</option>
						<option value="50"{if $recordsPerPage == 50} selected="selected"{/if}>50</option>
						<option value="75"{if $recordsPerPage == 75} selected="selected"{/if}>75</option>
						<option value="100"{if $recordsPerPage == 100} selected="selected"{/if}>100</option>
					</select>
					</div>
				</span>
			</div>
		</form>
*}

		<div id="logContainer">
			{include file="$logTable"}
		</div>

      {if $pageLinks.all}<div class="text-center">{$pageLinks.all}</div>{/if}
	</div>
{/strip}

<script>
	{literal}
	// Setup sorting for logs
	document.addEventListener('DOMContentLoaded', function() {
		var selectElement = document.getElementById('pagesize');

		// Add event listener for click to sort options
		selectElement.addEventListener('click', function(e) {
			let val = checkSelectedOption(this);
			if(val !== null) {
				//alert("Selected Value: " + val)
				Pika.changePageSize()
			}
		})

		// Add event listener for keypress (accessibility)
		selectElement.addEventListener('keypress', function(e) {
			let val = checkSelectedOption(this);
			if(e.key === 'Enter' && val !== null) {
				Pika.changePageSize()
			}
		})
	});
	{/literal}
</script>