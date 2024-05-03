{strip}
	<div class="page">
		{include file="MyAccount/patronWebNotes.tpl"}

		{* Alternate Mobile MyAccount Menu *}
		{include file="MyAccount/mobilePageHeader.tpl"}

		<span class='availableHoldsNoticePlaceHolder'></span>

		{if !$noHistory}
			{if $saved}
				<h1 role="heading" aria-level="1" class="h2">{translate text="history_saved_searches"}</h1>
				<table class="table table-bordered table-striped">
					<tr>
						<th>{translate text="history_id"}</th>
						<th>{translate text="history_time"}</th>
						<th>{translate text="history_search"}</th>
						<th>{translate text="history_limits"}</th>
						<th>{translate text="history_search_source"}</th>
						<th>{translate text="history_results"}</th>
						<th>{translate text="history_delete"}</th>
					</tr>
					{foreach item=info from=$saved name=historyLoop}
					<tr>
						<td>{$info.id}</td>
						<td>{$info.time}</td>
						<td><a href="{$info.url|escape}">{if empty($info.description)}{translate text="history_empty_search"}{else}{$info.description|escape}{/if}</a></td>
						<td>{foreach from=$info.filters item=filters key=field}{foreach from=$filters item=filter}
							<b>{translate text=$field|escape}</b>: {$filter.display|escape}<br>
						{/foreach}{/foreach}</td>
						<td>{$info.source}</td>
						<td>{$info.hits}</td>
						<td><button class="btn btn-xs btn-warning" onclick="return Pika.Account.deleteSearch('{$info.searchId}', 1, 1)">{translate text="history_delete_link"}</button></td>
					</tr>
					{/foreach}
				</table>
				<br>
			{/if}

			{if $links}
				<h2 class="h3">{translate text="history_recent_searches"}</h2>
				<table class="table table-bordered table-striped">
					<tr>
						<th>{translate text="history_time"}</th>
						<th>{translate text="history_search"}</th>
						<th>{translate text="history_limits"}</th>
						<th>{translate text="history_search_source"}</th>
						<th>{translate text="history_results"}</th>
						<th>{translate text="history_save"}</th>
					</tr>
					{foreach item=info from=$links name=historyLoop}
					<tr>
							<td>{$info.time}</td>
							<td><a href="{$info.url|escape}">{if empty($info.description)}{translate text="history_empty_search"}{else}{$info.description|escape}{/if}</a></td>
							<td>
							{foreach from=$info.filters item=filters key=field}
								{foreach from=$filters item=filter}
									<b>{translate text=$field|escape}</b>: {$filter.display|escape}<br>
								{/foreach}
							{/foreach}</td>
							<td>{$info.source}</td>
							<td>{$info.hits}</td>
							<td><button class="btn btn-xs btn-info" onclick="return Pika.Account.saveSearch('{$info.searchId}', 1, 1)">{translate text="history_save_link"}</button></td>
						</tr>
					{/foreach}
				</table>
				<br><a class="btn btn-warning" role="button" href="/Search/History?deleteUnsavedSearches=true"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span>&nbsp;{translate text="history_purge"}</a>
			{/if}

		{else}
			<h2 class="h3">{translate text="history_recent_searches"}</h2>
			{translate text="history_no_searches"}
		{/if}
	</div>
{/strip}
