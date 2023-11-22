{if $params.page}{assign var="pageNum" value=$params.page}{else}{assign var="pageNum" value=1}{/if}
{if $params.pagesize}{assign var="pageSize" value=$params.pagesize}{else}{assign var="pageSize" value=20}{/if}
{if $params.sort}{assign var="listSort" value=$params.sort}{else}{assign var="listSort" value=null}{/if}
<div style="text-align:left;">
	{if $message}<div class="error">{$message|translate}</div>{/if}
	<form action="/MyAccount/CiteList" method="get" class="form" id="citeListForm">
		<input type="hidden" name="listId" value="{$listId|escape}">
		<input type="hidden" name="page" id="myListPage" value="{$pageNum}">
		<input type="hidden" name="pagesize" id="myListPageSize" value="{$pageSize}">
		<input type="hidden" name="sort" id="myListSort" value="{$listSort}">
		<div class="form-group">
			<label for="citationFormat">{translate text='Citation Format'}:</label>
			<select name="citationFormat" id="citationFormat" class="form-control">
				{foreach from=$citationFormats item=formatName key=format}
					<option value="{$format}">{$formatName}</option>
				{/foreach}
			</select>
		</div>

	</form>
</div>