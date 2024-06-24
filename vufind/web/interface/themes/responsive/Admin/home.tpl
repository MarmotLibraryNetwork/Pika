{strip}
<div id="main-content" class="col-tn-12 col-xs-12">
	<h1 role="heading">Pika Solr Administration</h1>
	<hr>
	{if $PikaStatus}
	<h1 class="h2" role="heading" aria-level="1">Pika Status</h1>
		<table id="pika-status" class="table table-bordered">
			<tr class="{if $PikaStatus == 'critical'}danger{elseif $PikaStatus == 'warning'}warning{else}success{/if}">
				<th>{$PikaStatus|capitalize}</th>
			</tr>
			{foreach from=$PikaStatusMessages item=message}
				<tr>
					<td>{$message}{if strpos($message, "Index count") !== false} <a href="/Admin/Variables?objectAction=edit&name=solr_grouped_minimum_number_records"> Change minimum level</a>{/if}</td>
				</tr>
			{/foreach}
		</table>
	{/if}

	<h2>Searcher Core</h2>

	<h3>Grouped Index</h3>
	<table class="solr-info table table-bordered">
		<tr>
			<th>Document Count: </th>
			<td>{$data.grouped.index.numDocs}</td>
		</tr>
		<tr>
			<th>Index Size: </th>
			<td>{$data.grouped.index.size}</td>
		</tr>
		<tr>
			<th>Start Time: </th>
			<td>{$data.grouped.startTime|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
		</tr>
		<tr>
			<th>Last Modified: </th>
			<td>{$data.grouped.index.lastModified|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
		</tr>
		<tr>
			<th>Uptime: </th>
			<td>{$data.grouped.uptime|printms}</td>
		</tr>
		<tr>
			<th>Full Status: </th>
			<td><button class="btn btn-link" onclick="$('#searcherStatus').show();">Show full status</button>
				<div id="searcherStatus" style="display:none"><pre>{$data.grouped|print_r}</pre></div>
			</td>
		</tr>
	</table>

	{if !empty($data.genealogy) && !empty($data.genealogy.index.numDocs)}
		<h3>Genealogy Index</h3>
		<table class="solr-info table table-bordered">
			<tr>
				<th>Document Count: </th>
				<td>{$data.genealogy.index.numDocs}</td>
			</tr>
			<tr>
				<th>Index Size: </th>
				<td>{$data.genealogy.index.size}</td>
			</tr>
			<tr>
			<tr>
				<th>Start Time: </th>
				<td>{$data.genealogy.startTime|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Last Modified: </th>
				<td>{$data.genealogy.index.lastModified|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Uptime: </th>
				<td>{$data.genealogy.uptime|printms}</td>
			</tr>
			<tr>
				<th>Full Status: </th>
				<td><button class="btn btn-link" onclick="$('#searcherGenealogyStatus').show();">Show full status</button>
					<div id="searcherGenealogyStatus" style="display:none"><pre>{$data.genealogy|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

	{if $masterData}
		<hr>
		<h2>Indexer Core</h2>

		<h3>Grouped Index</h3>
		<table class="solr-info table table-bordered">
			<tr>
				<th>Document Count: </th>
				<td>{$masterData.grouped.index.numDocs}</td>
			</tr>
			<tr>
				<th>Index Size: </th>
				<td>{$masterData.grouped.index.size}</td>
			</tr>
			<tr>
			<tr>
				<th>Start Time: </th>
				<td>{$masterData.grouped.startTime|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Last Modified: </th>
				<td>{$masterData.grouped.index.lastModified|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Uptime: </th>
				<td>{$masterData.grouped.uptime|printms}</td>
			</tr>
			<tr>
				<th>Full Status: </th>
				<td><button class="btn btn-link" onclick="$('#masterStatus').show();">Show full status</button>
					<div id="masterStatus" style="display:none"><pre>{$masterData|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

	{if $archiveData}
		<hr>
		<h2>Archive Solr</h2>

		<h3>{$archiveSolrCore} Index</h3>
		<table class="solr-info table table-bordered">
			<tr>
				<th>Document Count: </th>
				<td>{$archiveData.collection1.index.numDocs}</td>
			</tr>
			<tr>
				<th>Index Size: </th>
				<td>{$archiveData.collection1.index.size}</td>
			</tr>
			<tr>
			<tr>
				<th>Start Time: </th>
				<td>{$archiveData.collection1.startTime|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Last Modified: </th>
				<td>{$archiveData.collection1.index.lastModified|date_format:"%b %d, %Y %l:%M:%S%p"}</td>
			</tr>
			<tr>
				<th>Uptime: </th>
				<td>{$archiveData.collection1.uptime|printms}</td>
			</tr>
			<tr>
				<th>Full Status: </th>
				<td><button class="btn btn-link" onclick="$('#archiveStatus').show();">Show full status</button>
					<div id="archiveStatus" style="display:none"><pre>{$archiveData|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

</div>
{/strip}