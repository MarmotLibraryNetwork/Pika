{strip}
{if $recordDriver}
	<div class="row">
		<div class="result-label col-xs-3">Grouped Work ID: </div>
		<div class="col-xs-9 result-value">
			{$recordDriver->getPermanentId()}
		</div>
	</div>

	<div class="row">
		<div class="col-xs-12">
			<div class="btn-group" role="group" aria-label="...">
				<a href="/GroupedWork/{$recordDriver->getPermanentId()}" class="btn btn-sm btn-default">Go To Grouped Work</a>
				<button onclick="return Pika.Record.reloadCover('{$recordDriver->getModule()}', '{$id}')" class="btn btn-sm btn-default">Reload Cover</button>
				<button onclick="return Pika.GroupedWork.reloadEnrichment('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Reload Enrichment</button>
				{if $staffClientUrl}
					<a href="{$staffClientUrl}" class="btn btn-sm btn-info">View in Staff Client</a>
				{/if}
			</div>
		</div>
	</div>
	<p></p>
    {*A little space between regular buttons and staff buttons*}
		{if $loggedIn}
				{if $userIsStaff}
					<div class="row">
						<div class="col-xs-12">
{*							<div class="btn-group" role="group" aria-label="...">*}
									{if $classicUrl}
										<a href="{$classicUrl}" class="btn btn-sm btn-info">View in Classic</a>
									{/if}
							<a href="/{$recordDriver->getModule()}/{$id|escape:"url"}/AJAX?method=downloadMarc" class="btn btn-sm btn-default">{translate text="Download Marc"}</a>
{*							</div>*}
                {if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles) || in_array('contentEditor', $userRoles))}
									<a href="/Admin/LibrarianReviews?objectAction=addNew&groupedWorkPermanentId={$recordDriver->getPermanentId()}" target="_blank" class="btn btn-sm btn-default">Add Librarian Review</a>
                {/if}
						</div>
					</div>
					<div class="row">
						<div class="col-xs-12">
{*							<div class="btn-group" role="group" aria-label="...">*}
							<button onclick="return Pika.GroupedWork.forceReindex('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Reindex</button>
							<button onclick="return Pika.GroupedWork.forceRegrouping('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Force Regrouping
							</button>
                {if $recordExtractable}
									<button onclick="return Pika.Record.forceReExtract('{$recordDriver->getModule()}', '{$id|escape}')" class="btn btn-sm btn-default">Force Extract from {$ils}</button>
                {/if}

                {if $userRoles && (in_array('opacAdmin', $userRoles) || in_array('cataloging', $userRoles))}
									<a href="/Admin/NonGroupedRecords?objectAction=addNew&recordId={$recordDriver->getId()}&source={$recordDriver->getRecordType()}&notes={$recordDriver->getShortTitle()|removeTrailingPunctuation|escape}%0A{$userDisplayName}, {$homeLibrary}, {$smarty.now|date_format}%0A" target="_blank" class="btn btn-sm btn-default">UnMerge from Work</a>
                {/if}

{*							</div>*}
						</div>
					</div>
            {if $enableArchive && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('archives', $userRoles))}
							<div class="row">
								<div class="col-xs-12">
{*									<div class="btn-group" role="group" aria-label="...">*}
									<button onclick="return Pika.GroupedWork.reloadIslandora('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">Clear Islandora Cache</button>
{*									</div>*}
								</div>
							</div>
            {/if}

        {/if} {*End of userIsStaff *}

    {/if} {* End of loggedIn*}

	{* QR Code *}
	{include file="Record/qrcode.tpl"}

{/if}

		{if $hooplaExtract}
			<h3>Hoopla Extract Information</h3>
			{if $matchedByAccessUrl}
				<div class="alert alert-warning">Extract Information was matched by id in access url instead of record id.</div>
			{/if}
			<table class="table-striped table table-condensed notranslate">
					{foreach from=$hooplaExtract key='field' item='values'}
							{if $field != 'id'}{* this id is the database table id, and will confuse most users as the hoopla id*}
								<tr>
									<th>{$field|escape}</th>
									<td>
											{if $field == 'dateLastUpdated'}
												{$values|date_format:"%b %d, %Y %r"}
											{elseif !empty($value)}
												{implode subject=$values glue=', ' sort=true}
											{/if}
									</td>
								</tr>
							{/if}
					{/foreach}
			</table>
		{/if}

    {if $marcRecord}
			<h3>Record Information</h3>
			<table class="table-striped table table-condensed notranslate">
			{if isset($lastRecordExtractTime)}
				<tr>
					<th>Last {$ils} Extract Time</th>
					<td>{if $lastRecordExtractTime == 'null'}Marked for Re-extraction{else}{$lastRecordExtractTime|date_format:"%b %d, %Y %r"}{/if}</td>
					{* $lastRecordExtractTime variable is set to string 'null' to signal that is marked for re-extraction *}
				</tr>
			{/if}
			{if $recordExtractMarkedDeleted}
				<tr>
					<th>{$ils} Extract Marked Suppressed/Deleted Date</th>
					<td>{$recordExtractMarkedDeleted|date_format:"%b %d, %Y"}</td>
				</tr>
			{/if}
				<tr>
					<th>Last File Modification Time</th>
					<td>{$lastMarcModificationTime|date_format:"%b %d, %Y %r"}</td>
				</tr>
				<tr>
					<th>Last Grouped Work Modification Time</th>
					<td>{if $lastGroupedWorkModificationTime == 'null'}Marked for re-index{else}{$lastGroupedWorkModificationTime|date_format:"%b %d, %Y %r"}{/if}</td>
				</tr>
			</table>

			<div id="formattedMarcRecord">
				<h3>MARC Record</h3>
				<table class="table table-condensed table-bordered notranslate">
					<tbody>
						{*Output leader*}
						<tr><th colspan="3">LEADER</th><td>{$marcRecord->getLeader()}</td></tr>
						{foreach from=$marcRecord->getFields() item=field}
							{if get_class($field) == "File_MARC_Control_Field"}
								<tr><th colspan="3">{$field->getTag()}</th><td>{$field->getData()|escape|replace:' ':'&nbsp;'}</td></tr>
							{else}
								<tr><th>{$field->getTag()}</th>{if $field->getIndicator(1) == ' '}<td></td>{else}<th>{$field->getIndicator(1)}</th>{/if}{if $field->getIndicator(2) == ' '}<td></td>{else}<th>{$field->getIndicator(2)}</th>{/if}<td style="overflow-wrap: anywhere">
												{* Have to use anywhere overflow instead of break-word because item record tags usually don't contain
												anything that would be a word break *}
										{* Can't use empty <th> for accessiblity. "Table header elements should have visible text. Ensure that the table header can be used by screen reader users. If the element is not a header, marking it up with a `td` is more appropriate." *}
								{foreach from=$field->getSubfields() item=subfield}
								<strong>&nbsp;|{$subfield->getCode()}&nbsp;</strong>{$subfield->getData()|escape}
								{/foreach}
								</td></tr>
							{/if}

						{/foreach}
					</tbody>
				</table>
			</div>
{/if}

{if $solrRecord}
	<div id="formattedSolrRecord">
		<h3>Solr Record</h3>
		<dl>
			{foreach from=$solrRecord key='field' item='values'}
				<dt>{$field|escape}</dt>
				<dd>{implode subject=$values glue=", "}</dd>
			{/foreach}
		</dl>
	</div>
{/if}
{/strip}