{strip}
<button onclick="return Pika.GroupedWork.reloadCover('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Cover</button>
<button onclick="return Pika.GroupedWork.reloadEnrichment('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Enrichment</button>
		{if $loggedIn && $userIsStaff}
			<button onclick="return Pika.GroupedWork.forceReindex('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Reindex</button>
			<button onclick="return Pika.GroupedWork.forceRegrouping('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Regrouping</button>

				{if $userRoles && in_array('opacAdmin', $userRoles) || in_array('cataloging', $userRoles)}
					<a href="/Admin/MergedGroupedWorks?objectAction=addNew&sourceGroupedWorkId={$recordDriver->getPermanentId()}&notes={$recordDriver->getTitle()|removeTrailingPunctuation|escape}%0A{$userDisplayName}, {$homeLibrary}, {$smarty.now|date_format}%0A"
						 target="_blank" class="btn btn-sm btn-default">Merge this Work to another
					</a>
				{/if}
		{/if}
    {if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles) || in_array('contentEditor', $userRoles))}
			<a href="/Admin/LibrarianReviews?objectAction=addNew&groupedWorkPermanentId={$recordDriver->getPermanentId()}" target="_blank" class="btn btn-sm btn-default">Add Librarian Review</a>
    {/if}
		{if $loggedIn && $enableArchive && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('archives', $userRoles))}
	<button onclick="return Pika.GroupedWork.reloadIslandora('{$recordDriver->getUniqueID()}')" class="btn btn-sm btn-default">Clear Islandora Cache</button>
{/if}


	{* QR Code *}
{if $showQRCode}
	<div id="record-qr-code" class="text-center hidden-xs visible-md"><img src="{$recordDriver->getQRCodeUrl()}" alt="QR Code for Record"></div>
{/if}

<h4>Grouping Information</h4>
<table class="table-striped table table-condensed notranslate">
	<tr>
		<th>Grouped Work ID</th>
		<td>{$recordDriver->getPermanentId()}</td>
	</tr>
	{foreach from=$groupedWorkDetails key='field' item='value'}
	<tr>
		<th>{$field|escape}</th>
		<td>
			{$value}
		</td>
	</tr>
	{/foreach}
</table>

<h4>Solr Details</h4>
<table class="table-striped table table-condensed notranslate">
	{foreach from=$details key='field' item='values'}
		<tr>
			{if strpos($field, "scoping_details") !== false}
				<td colspan="2">
					<strong>{$field|escape}</strong>
				<table id="scoping_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-x: scroll; font-size: smaller">{*TODO: style rule should go in css *}
					<tr>
						<th>Bib Id</th><th>Item Id</th><th>Grouped Status</th><th>Status</th><th>Locally Owned</th><th>Available</th><th>Holdable</th><th>Bookable</th><th>In Library Use Only</th><th>Library Owned</th><th>Holdable PTypes</th><th>Bookable PTypes</th><th>Local Url</th>
					</tr>
					{foreach from=$values item="item"}
					<tr>
						{*{assign var="item" value=$item|rtrim:"|"}*}
						{assign var="details" value="|"|explode:$item}
						{foreach from=$details item='detail'}
						{*{foreach from=explode($values, "|") item='detail'}*}
						<td>{$detail|replace:',':', '}</td>
					{/foreach}
					</tr>
					{/foreach}
				</table>
				</td>
			{elseif strpos($field, "item_details") !== false}
				<td colspan="2">
					<strong>{$field|escape}</strong>
				<table id="item_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-x: scroll; font-size: x-small">{*TODO: style rule should go in css *}
					<tr>
						<th>Bib Id</th><th>Item Id</th><th>Shelf Loc</th><th>Call Num</th><th>Format</th><th>Format Category</th><th>Num Copies</th><th>Is Order Item</th><th>Is eContent</th><th>eContent Source</th><th>eContent File</th><th>eContent URL</th><th>subformat</th><th>Detailed Status</th><th>Last Checkin</th><th>Location</th><th>Sub-location</th>
					</tr>
					{foreach from=$values item="item"}
					<tr>
						{*{assign var="item" value=$item|rtrim:"|"}*}
						{assign var="details" value="|"|explode:$item}
						{foreach from=$details item='detail'}
						{*{foreach from=explode($values, "|") item='detail'}*}
						<td>{$detail|replace:',':', '}</td>
					{/foreach}
					</tr>
					{/foreach}
				</table>
				</td>
			{elseif strpos($field, "record_details") !== false}
				<td colspan="2">
					<strong>{$field|escape}</strong>
				<table id="record_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-x: scroll; font-size: smaller">{*TODO: style rule should go in css *}
					<tr>
						<th>Bib Id</th><th>Format</th><th>Format Category</th><th>Edition</th><th>Language</th><th>Publisher</th><th>Publication Date</th><th>Physical Description</th>
					</tr>
					{foreach from=$values item="item"}
					<tr>
						{*{assign var="item" value=$item|rtrim:"|"}*}
						{assign var="details" value="|"|explode:$item}
						{foreach from=$details item='detail'}
						{*{foreach from=explode($values, "|") item='detail'}*}
						<td>{$detail|replace:',':', '}</td>
					{/foreach}
					</tr>
					{/foreach}
				</table>
				</td>
			{else}
			<th>{$field|escape}</th>
			<td>
				{implode subject=$values glue='<br>' sort=true}
{*				{implode subject=$values glue=', ' sort=true}*}
			</td>
			{/if}
		</tr>
	{/foreach}
</table>
{/strip}