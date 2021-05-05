{strip}
<div id="main-content" class="col-tn-12 col-xs-12">
	<h1>Pika Administration</h1>
	<hr>
	{if $PikaStatus}
	<h2>Pika Status</h2>
		<table class="table table-bordered">
			<tr class="{if $PikaStatus == 'critical'}danger{elseif $PikaStatus == 'warning'}warning{else}success{/if}">
				<th>{$PikaStatus|capitalize}</th>
			</tr>
			{foreach from=$PikaStatusMessages item=message}
				<tr>
					<td>{$message}</td>
				</tr>
			{/foreach}
		</table>
	{/if}

	<h2>Searcher Core</h2>

	<h3>Grouped Index</h3>
	<table class="table table-bordered">
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
			<td><a onclick="$('#searcherStatus').show();">Show full status</a>
				<div id="searcherStatus" style="display:none"><pre>{$data.grouped|print_r}</pre></div>
			</td>
		</tr>
	</table>

	{if !empty($data.genealogy) && !empty($data.genealogy.index.numDocs)}
		<h3>Genealogy Index</h3>
		<table class="table table-bordered">
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
				<td><a onclick="$('#searcherGenealogyStatus').show();">Show full status</a>
					<div id="searcherGenealogyStatus" style="display:none"><pre>{$data.genealogy|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

	{if $masterData}
		<hr>
		<h2>Indexer Core</h2>

		<h3>Grouped Index</h3>
		<table class="table table-bordered">
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
				<td><a onclick="$('#masterStatus').show();">Show full status</a>
					<div id="masterStatus" style="display:none"><pre>{$masterData|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

	{if $archiveData}
		<hr>
		<h2>Archive Solr</h2>

		<h3>{$archiveSolrCore} Index</h3>
		<table class="table table-bordered">
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
				<td><a onclick="$('#archiveStatus').show();">Show full status</a>
					<div id="archiveStatus" style="display:none"><pre>{$archiveData|print_r}</pre></div>
				</td>
			</tr>
		</table>
	{/if}

</div>
{/strip}