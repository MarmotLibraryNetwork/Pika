<h3 id="listTitle"><a href="/MyAccount/MyList/{$favList->id}">{$favList->title|escape:"html"}</a></h3>

{if $favList->description}
	<div class="listDescription alignleft" id="listDescription">{$favList->description|escape}</div>
{/if}
<div id="listTopButtons" class="btn-toolbar">
	<div class="btn-group">
		<a value="viewList" id="FavEdit" class="btn btn-sm btn-info" href="/MyAccount/MyList/{$favList->id}">Return to List</a>
	</div>
</div>
<div class="alert alert-info">Citations in {$citationFormat} format.</div>
{if $citations}
	<div id="searchInfo">
		{foreach from=$citations item=citation}
			<div class="citation">
			{$citation}
			</div>
			<br />
		{/foreach}
		{if $recordCount}
			{translate text="Showing"}
			<b>{$recordStart}</b> - <b>{$recordEnd}</b>
			{translate text='of'} <b>{$recordCount}</b>
		{/if}

	</div>
{else}
	{translate text='This list does not have any titles to build citations for.'}
{/if}
<div class="alert alert-warning">
	<p>{translate text="Citation formats are based on standards as of July 2010.  Citations contain only title, author, edition, publisher, and year published."}</p>
	<p>{translate text="Citations should be used as a guideline and should be double checked for accuracy."}</p>
	<p>{translate text="For titles that are available in multiple formats you can view more detailed citations by viewing the record for the specific format."}</p>
</div>

