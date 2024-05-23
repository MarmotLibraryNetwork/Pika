{strip}
<button onclick="return Pika.GroupedWork.reloadCover('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Cover</button>
<button onclick="return Pika.GroupedWork.reloadEnrichment('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Enrichment</button>
	<button onclick="return Pika.GroupedWork.reloadNovelistData('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload NoveList Data</button>
		{if $loggedIn && $userIsStaff}
			<button onclick="return Pika.GroupedWork.forceReindex('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Reindex</button>
			<button onclick="return Pika.GroupedWork.forceRegrouping('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Regrouping</button>

				{if $userRoles && in_array('opacAdmin', $userRoles) || in_array('cataloging', $userRoles)}
					<a href="/Admin/MergedGroupedWorks?objectAction=addNew&sourceGroupedWorkId={$recordDriver->getPermanentId()}&notes={$recordDriver->getTitle()|removeTrailingPunctuation|escape:'url'}%0A{$userDisplayName}, {$homeLibrary}, {$smarty.now|date_format}%0A"
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
		<td>{$value}</td>
	</tr>
	{/foreach}
</table>
<div class="enrichmentInfo"{if $novelistPrimaryISBN} style="display: none"{/if}>
	<h4>Enrichment Information</h4>
	<div class="row">
		<div class="col-xs-6 col-sm-3"><strong>Novelist Primary ISBN</strong></div>
		<div id="novelistPrimaryISBN" class="col-xs-6 col-sm-9">{$novelistPrimaryISBN}</div>
	</div>
	<div class="row">
		<div class="col-xs-6 col-sm-3"><strong>Review ISBN</strong></div>
		<div id="isbnForReviews" class="col-xs-6 col-sm-9"></div>
	</div>
</div>
<h4>Solr Fields</h4>
	{foreach from=$details key='field' item='values'}
			<div class="row" style="border: solid #ddd; border-width: 1px 0 0 0">
			{if strpos($field, "scoping_details") === false
			&& strpos($field, "item_details") === false
			&& strpos($field, "record_details") === false}
				<div class="col-xs-6 col-sm-4"><strong>{$field|escape}</strong></div>
				<div class="col-xs-6 col-sm-8">
				{implode subject=$values glue='<br>' sort=true}
				</div>
				<div class="clearfix visible-sm-block"></div>
      {/if}
			</div>
	{/foreach}
	{* Display Details tables last *}
	<h4>Solr Details Tables</h4>
		{foreach from=$details key='field' item='values'}
			<div class="row" style="border: solid #ddd; border-width: 1px 0 0 0">
			{if strpos($field, "scoping_details") !== false}
				<div class="col-tn-12">
				<h4>{$field|escape}</h4>
				<table id="scoping_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-wrap: anywhere; font-size: smaller;table-layout: fixed">
					<tr>
						<th>Bib Id</th><th>Item Id</th><th>Grouped Status</th><th>Status</th><th>Locally Owned</th><th>Available</th><th>Holdable</th><th>Bookable</th><th>In Library Use Only</th><th>Library Owned</th><th>Holdable PTypes</th><th>Bookable PTypes</th><th>Local Url</th>
					</tr>
					{foreach from=$values item="item"}
					<tr>
						{assign var="details" value="|"|explode:$item}
						{foreach from=$details item='detail' key="k"}
						<td{if in_array($k, array(0,1))} style="overflow-wrap: anywhere; min-width: 50px" {/if}>{if in_array($k, array(4,5,6,7,8,9))}{if $detail}true{else}false{/if}{else}{$detail|replace:',':', '}{/if}</td>
					{/foreach}
					</tr>
					{/foreach}
				</table>
				</div>
			{elseif strpos($field, "item_details") !== false}
				<div class="col-tn-12">
				<h4>{$field|escape}</h4>
					<table id="item_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-wrap: break-word; font-size: smaller;table-layout: fixed">
						<tr>
							<th>Bib Id</th><th>Item Id</th><th>Shelf Location</th><th>Call Num</th><th>Format</th><th>Format Category</th><th>Num Copies</th><th>Is Order Item</th><th>Is eContent</th><th>eContent Source</th>{*<th>eContent File</th>*}<th>eContent URL</th>{*<th>subformat</th>*}<th>Detailed Status</th><th>Last Checkin</th><th>Location</th>{*<th>Sub-location</th>*}
						</tr>
              {foreach from=$values item="item"}
								<tr>
                    {assign var="details" value="|"|explode:$item}
                    {foreach from=$details item='detail' key="k"}
											<td{if in_array($k, array(0,1,10))} style="overflow-wrap: anywhere; min-width: 50px" {/if}>{if in_array($k, array(7,8))}{if $detail}true{else}false{/if}{else}{$detail|replace:',':', '}{/if}</td>
                    {/foreach}
								</tr>
              {/foreach}
					</table>
				</div>
			{elseif strpos($field, "record_details") !== false}
				<div class="col-tn-12">
				<h4>{$field|escape}</h4>
					<table id="record_details" class="table-striped table table-condensed table-bordered notranslate" style="overflow-wrap: break-word; font-size: smaller;table-layout: fixed">
						<tr>
							<th>Bib Id</th><th>Format</th><th>Format Category</th><th>Edition</th><th>Language</th><th>Publisher</th><th>Publication Date</th><th>Physical Description</th><th>Abridged</th>
						</tr>
              {foreach from=$values item="item"}
								<tr>
                    {assign var="details" value="|"|explode:$item}
                    {foreach from=$details item='detail' key="k"}
											<td{if $k==0} style="overflow-wrap: anywhere; min-width: 50px" {/if}>{$detail|replace:',':', '}</td>
                    {/foreach}
								</tr>
              {/foreach}
					</table>
				</div>
      {/if}
			</div>
	{/foreach}
{/strip}