{if $smarty.get.page}{assign var="pageNum" value=$smarty.get.page}{else}{assign var="pageNum" value=1}{/if}
{if $smarty.get.pagesize}{assign var="pageSize" value=$smarty.get.pagesize}{else}{assign var="pageSize" value=20}{/if}
{if $smarty.get.sort}{assign var="listSort" value=$smarty.get.sort}{else}{assign var="listSort" value=null}{/if}
<div style="text-align:left;">
	{if $message}<div class="error">{$message|translate}</div>{/if}
	<form action="/MyAccount/CiteList" method="get" class="form" id="citeListForm">
		<input type="hidden" name="listId" value="{$listId|escape}">
		<input type="hidden" name="myListPage" id="myListPage" value="{$pageNum}">
		<input type="hidden" name="myListPageSize" id="myListPageSize" value="{$pageSize}">
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