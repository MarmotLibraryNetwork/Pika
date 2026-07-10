{strip}
	<div class="col-sm-12">
		<h1 role="heading" aria-level="1" class="h2">Import Lists from Classic Catalog</h1>
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
		<div class="card card-body">
			<p class="alert alert-warning">The following errors occurred. For any titles that failed to import, you can search the catalog for these titles to re-add to your lists.</p>
			<ul class="list-group">
				{foreach from=$importResults.errors item=error}
					<li class="list-group-item">{$error}</li>
				{/foreach}
			</ul>
		</div>
{* TODO: use with newer bootstrap
		<div class="card card-body">
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
		<a href="/MyAccount/MyLists/" title="Return to My Lists" class="btn btn-outline-secondary btn-sm">Return to My Lists</a>
	</div>
{/strip}