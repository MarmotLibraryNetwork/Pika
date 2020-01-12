{strip}
	<div class="col-xs-12">
		<h3>Import Lists from Classic Catalog</h3>
	{if $importResults && $importResults.success}
		<div class="alert alert-success">
			<span class="badge">{$importResults.totalTitles}</span> title{if $importResults.totalTitles !=1}s{/if} from <span class="badge">{$importResults.totalLists}</span> list{if $importResults.totalLists != 1}s{/if} were successfully imported.
		</div>
	{else}
		<div class="alert alert-danger">
			Sorry your lists could not be imported.
		</div>
	{/if}
	{if $importResults.errors}
		<div class="well">
			<p class="alert alert-warning">The following errors occurred. For any titles that failed to import, you can search the catalog for these titles to re-add to your lists.</p>
			<ul class="list-group">
				{foreach from=$importResults.errors item=error}
					<li class="list-group-item">{$error}</li>
				{/foreach}
			</ul>
		</div>
{* TODO: use with newer bootstrap
		<div class="well">
			<p>The following errors occurred. For titles that failed to import, you can search the catalog for these titles to re-add them to your lists.</p>
			<p>
			<ul class="list-group">
				{foreach from=$importResults.errors item=error}
					<li class="list-group-item list-group-item-warning">{$error}</li>
				{/foreach}
			</ul>
			</p>
		</div>
*}
	{/if}
	</div>
{/strip}