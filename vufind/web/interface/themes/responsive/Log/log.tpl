{strip}
	<div id="main-content" class="col-md-12">
		<h3>{$pageTitleShort}</h3>
		<hr>

		{if $alert}{$alert}{/if}

		<h4>Filter by</h4>
		<form class="navbar form-inline row">
        {if !empty($filterLabel)}
					<div class="form-group col-xs-7">
						<label for="filterCount" class="control-label">{$filterLabel}:&nbsp;</label>
						<input style="width: 125px;" id="filterCount" name="filterCount" type="number" min="0" class="form-control" {if !empty($smarty.request.filterCount)} value="{$smarty.request.filterCount}"{/if}>
						<button class="btn btn-primary" type="submit">Go</button>
					</div>
        {/if}
			<div class="form-group col-xs-5 pull-right">
				<span class="pull-right">
					<label for="pagesize" class="control-label">Entries Per Page:&nbsp;</label>
					<select id="pagesize" name="pagesize" class="pagesize form-control input-sm" onchange="VuFind.changePageSize()">
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
				<div class="form-group col-xs-7">
					<div class="input-group">
					<label for="filterCount" class="control-label input-group-addon">{$filterLabel}</label>
					<input id="filterCount" name="filterCount" type="number" min="0" class="form-control" {if !empty($smarty.request.filterCount)} value="{$smarty.request.filterCount}"{/if}>
					<span class="input-group-btn"><button class="btn btn-primary" type="submit">Go</button></span>
				</div>
				</div>
      {/if}
			<div class="form-group col-xs-5 pull-right">
				<span class="pull-right">
					<div class="input-group">
					<label for="pagesize" class="control-label input-group-addon">Entries Per Page</label>
					<select id="pagesize" name="pagesize" class="pagesize form-control input-sm" onchange="VuFind.changePageSize()">
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