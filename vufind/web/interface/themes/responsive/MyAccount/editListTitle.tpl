{strip}
	{if $params.page}{assign var="pageNum" value=$params.page}{else}{assign var="pageNum" value=1}{/if}
	{if $params.pagesize}{assign var="pageSize" value=$params.pagesize}{else}{assign var="pageSize" value=20}{/if}
	{if $params.sort}{assign var="listSort" value=$params.sort}{else}{assign var="listSort" value=""}{/if}
<div id="page-content" class="content">
	<div id="main-content">
		{if $error}
			<div class="alert alert-danger">{$error}</div>
		{else}

			<div class="record">

				<h1 role="heading" id="resourceTitle">{$recordDriver->getTitle()|escape:"html"}</h1>

				<form method="post" id="listEntryEditForm" action="/MyAccount/Edit" class="form-horizontal">
					<input type="hidden" id="listEntry" name="listEntry" value="{$listEntry->id}">
					<input type="hidden" id="list_id" name="list_id" value="{$list->id}">
					<input type="hidden" id="myListPage" name="page" value="{$pageNum}">
					<input type="hidden" id = "myListPageSize" name="pagesize" value="{$pageSize}">
					<input type="hidden" id = "myListSort" name="sort" value="{$listSort}">
					<div>
						<div class="form-group">
							<label for="listName" class="col-sm-3">{translate text='List'}: </label>
							<div class="col-sm-9">{$list->title|escape:"html"}</div>
						</div>

						<div class="form-group">
							<label for="listNotes" class="col-sm-3">{translate text='Notes'}: </label>
							<div class="col-sm-9">
								<textarea id="listNotes" name="notes" rows="3" cols="50" class="form-control">{$listEntry->notes|escape:"html"}</textarea>
							</div>
						</div>

						<div class="form-group">
							<div class="col-sm-3"></div>
							<div class="col-sm-9">
								<input type="submit" name="submit" value="{translate text='Save'}" class="btn btn-primary">
							</div>
						</div>
					</div>
				</form>

			</div>
		{/if}

	</div>
</div>
{/strip}