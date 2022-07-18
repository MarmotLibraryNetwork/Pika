{*{strip}*}
{if $smarty.get.page}{assign var="pageNum" value=$smarty.get.page}{else}{assign var="pageNum" value=1}{/if}
{if $smarty.get.pagesize}{assign var="pageSize" value=$smarty.get.pagesize}{else}{assign var="pageSize" value=20}{/if}
{if $smarty.get.sort}{assign var="listSort" value=$smarty.get.sort}{else}{assign var="listSort" value=null}{/if}
{if $allowEdit}
<a href="/MyAccount/MyLists/" title="Return to My Lists" class="btn btn-default btn-sm">Return to My Lists</a>
{/if}
{if isset($favList)}
	<form action="/MyAccount/MyList/{$favList->id}" id="myListFormHead">
		<div>
			{foreach from=$smarty.get.filter item="filter"}<input type="hidden" name="filter[]" value="{$filter|escape}">{/foreach}
			<input type="hidden" name="myListActionHead" id="myListActionHead">
			<input type="hidden" name="myListActionData" id="myListActionData">
			<input type="hidden" name="myListPage" id="myListPage">
			<input type="hidden" name="myListPageSize" id="myListPageSize">
			<input type="hidden" name="myListSort" id="myListSort">
			<h3 id="listTitle">{$favList->title|escape:"html"}</h3>
			{if $notes}
				<div id="listNotes" class="alert alert-info">
				{foreach from=$notes item="note"}
					<div class="listNote">{$note}</div>
				{/foreach}
				</div>
			{/if}

			{if $favList->deleted == 1}
				<p class="alert alert-danger">Sorry, this list has been deleted.</p>
			{else}
				{if $favList->description}<div class="listDescription alignleft" id="listDescription">{$favList->description|escape}</div>{/if}
				{if $allowEdit}
					<div id="listEditControls" style="display:none" class="collapse">
						<div class="form-group">
							<label for="listTitleEdit" class="control-label">Title: </label>
							<input type="text" id="listTitleEdit" name="newTitle" value="{$favList->title|escape:"html"}" maxlength="255" size="80" class="form-control">
						</div>
						<div class="form-group">
							<label for="listDescriptionEdit" class="control-label">Description: </label>&nbsp;
							<textarea name="newDescription" id="listDescriptionEdit" rows="3" cols="80" class="form-control">{$favList->description|escape:"html"}</textarea>
						</div>
						<div class="form-group">

							<label for="defaultSort" class="control-label">Default Sort: </label>
							<select id="defaultSort" name="defaultSort" class="form-control">
								{foreach from=$defaultSortList item=sortValue key=sortLabel}
									<option value="{$sortLabel}"{if $sortLabel == $defaultSort} selected="selected"{/if}>
										{translate text=$sortValue}
									</option>
								{/foreach}
							</select>

						</div>
					</div>
				{/if}
				<div class="clearer"></div>
				<div id="listTopButtons" class="btn-toolbar">
					{if $allowEdit}
						<div class="btn-group">
							<button value="editList" id="FavEdit" class="btn btn-sm btn-info" onclick="return Pika.Lists.editListAction()">Edit List</button>
							<button type="button" class="btn btn-sm btn-default btn-toolbar dropdown-toggle" data-toggle="dropdown" area-expanded="false">Share <span class="caret"></span></button>
							<ul class=" dropdown-menu dropdown-menu-right" role="menu">
								{if $favList->public}<li><a href="#" value="emailList" id="FavEmail"  onclick='return Pika.Lists.emailListAction("{$favList->id}")'>Email List</a></li>{/if}
								<li><a href="#" value="printList" id="FavPrint"  onclick='return Pika.Lists.printListAction()'>Print List</a></li>
								{if $favList->public}<li><a href="#" id="copyList" onclick="return Pika.Lists.copyList({$favlist->id})">Copy List</a></li>{/if}
								<li><a href="#" value="exportToExcel" id="FavExcel" onclick='return Pika.Lists.exportListAction("{$favList->id}", {$pageNum}, {$pageSize},"{$listSort}");'>Export to Excel</a></li>
								{if $favList->public}<li><a href="https://twitter.com/compose/tweet?text={$favList->title|escape:"html"}+{$url|escape:"html"}/MyAccount/MyList/{$favList->id}" id="Twitter Share">Share on Twitter <img class="pull-right" src="{img filename='twitter-icon.png'}" alt="Share on Twitter"></a></li>{/if}
								{if $favList->public}<li><a href="http://www.facebook.com/sharer/sharer.php?u={$url|escape:"html"}/MyAccount/MyList/{$favList->id}" id="">Share on Facebook <img src="{img filename='facebook-icon.png'}" alt="Share on Facebook"></a></li>{/if}
								{if $favList->public}<li>{include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|escape:"html"|cat:"/MyAccount/MyList/"|cat:$favList->id description="See My List '"|cat:$favList->title|cat:"' at $homeLibrary" linkText="Pin on Pinterest" imgClass="pull-right"}</li>{/if}

							</ul>
						</div>
						<div class="btn-group">
							<button value="saveList" id="FavSave" class="btn btn-sm btn-primary" style="display:none" onclick='return Pika.Lists.updateListAction({$pageNum}, {$pageSize},"{$listSort}")'>Save Changes</button>
						</div>
						<div class="btn-group">
							<button value="batchAdd" id="FavBatchAdd" class="btn btn-sm btn-default" onclick='return Pika.Lists.batchAddToListAction({$favList->id})'>Add Multiple Titles</button>

							{if $favList->public == 0}
								<button value="makePublic" id="FavPublic" class="btn btn-sm btn-default" onclick='return Pika.Lists.makeListPublicAction({$pageNum}, {$pageSize},"{$listSort}")'>Make Public</button>

							{else}
								<button value="adminOptions" id="adminOptions" class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Admin Options <span class="caret"></span></button>
								<ul class="dropdown-menu dropdown-menu-right" role="menu">
									<li><a href="#"  id="FavPrivate"  onclick='return Pika.Lists.makeListPrivateAction({$pageNum}, {$pageSize},"{$listSort}")'>Make Private</a></li>
								{if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('libraryManager', $userRoles) || in_array('contentEditor', $userRoles))}
								<li><a href="#"  id="FavCreateWidget" onclick="return Pika.ListWidgets.createWidgetFromList('{$favList->id}')">Create Widget</a></li>
								{/if}
								{if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('contentEditor', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles))}
									<li><a href="#" id="FavHome"  onclick="return Pika.Lists.addToHomePage('{$favList->id}')">{translate text='Add To Home Page'}</a></li>
									{if $favList->public !=0}
										<li><a href="#" id="TransferList" onclick="return Pika.Lists.transferListToUser('{$favList->id}')">{translate text='Transfer List'}</a></li>
									{/if}
								{/if}

								</ul>

							{/if}
						</div>
					{/if}
					<div class="btn-group">
						{if $favList->public !=0 && $allowEdit == 0}

							<div class="btn-toolbar">


								<div class="btn-group btn-group-sm">
									<div class="share-tools">
										<span class="share-tools-label hidden-inline-xs">SHARE LIST</span>
										<a href="#" onclick="return Pika.Lists.emailListAction({$favList->id})" title="share via e-mail">
											<img src="{img filename='email-icon.png'}" alt="E-mail this" style="cursor:pointer;">
										</a>
										<a href="#" id="FavExcel" onclick="return Pika.Lists.exportListFromLists('{$myList.id}');" title="Export List to Excel">
											<img src="{img filename='excel.png'}" alt="Export to Excel">
										</a>
										<a href="https://twitter.com/compose/tweet?text={$favList->title|escape:"html"}+{$url|escape:"html"}/MyAccount/MyList/{$favList->id}" target="_blank" title="Share on Twitter">
											<img src="{img filename='twitter-icon.png'}" alt="Share on Twitter">
										</a>
										<a href="http://www.facebook.com/sharer/sharer.php?u={$url|escape:"html"}/MyAccount/MyList/{$favList->id}" target="_blank" title="Share on Facebook">
											<img src="{img filename='facebook-icon.png'}" alt="Share on Facebook">
										</a>

										{include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|escape:"html"|cat:"/MyAccount/MyList/"|cat:$favList->id description="See My List '"|cat:$favList->title|cat:"' at $homeLibrary"}

									</div>
								</div>
								<div class="btn-group btn-group-sm">
									<button value="copyList" id="copyList" class="btn btn-sm btn-default" onclick="return Pika.Lists.copyList({$favList->id})">Copy List</button>
								</div>

							</div>
						{/if}
					</div>
					<div class="btn-group">

						<button value="citeList" id="FavCite" class="btn btn-sm btn-default" onclick='return Pika.Lists.citeListAction("{$favList->id}")'>Generate Citations</button>

						<div class="btn-group" role="group">

							<button type="button" class="btn btn-sm btn-default btn-info dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Sort &nbsp;<span class="caret"></span></button>
							<ul class="dropdown-menu dropdown-menu-right" role="menu">
								{foreach from=$sortList item=sortData}
									<li>
										<a{if !$sortData.selected} href="{$sortData.sortUrl|escape}"{/if}> {* only add link on un-selected options *}
											{translate text=$sortData.desc}
											{if $sortData.selected} <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>{/if}
										</a>
									</li>
								{/foreach}
							</ul>

						</div>

					</div>


					{if $allowEdit}
						<div class="btn-group">
							<button value="deleteMarked" id="markedDelete" class="btn btn-sm btn-default" onclick='return Pika.Lists.deleteListItems({literal}$("input[name=marked]:checked"){/literal},{$pageNum}, {$pageSize}, "{$listSort}");'>Delete Selected</button>
							<button value="clearList" id="ClearLists" class="btn btn-sm btn-default" onclick='return Pika.Lists.deleteAllListItemsAction({$pageNum}, {$pageSize},"{$listSort}");'>Clear List</button>
							<button value="deleteList" id="FavDelete" class="btn btn-sm btn-danger" onclick='return Pika.Lists.deleteListAction({$pageNum}, {$pageSize},"{$listSort}");'>Delete List</button>
						</div>

					{/if}
				</div>
			{/if}
		</div>
	</form>

	{if $favList->deleted == 0}
		{if $resourceList}
			<form class="navbar form-inline">
				<label for="pagesize" class="control-label">Records Per Page</label>&nbsp;
				<select id="pagesize" class="pagesize form-control{* input-sm*}" onchange="Pika.changePageSize()">
					<option value="20"{if $recordsPerPage == 20} selected="selected"{/if}>20</option>
					<option value="40"{if $recordsPerPage == 40} selected="selected"{/if}>40</option>
					<option value="60"{if $recordsPerPage == 60} selected="selected"{/if}>60</option>
					<option value="80"{if $recordsPerPage == 80} selected="selected"{/if}>80</option>
					<option value="100"{if $recordsPerPage == 100} selected="selected"{/if}>100</option>
				</select>
				<label for="hideCovers" class="control-label checkbox pull-right"> Hide Covers <input id="hideCovers" type="checkbox" onclick="Pika.Account.toggleShowCovers(!$(this).is(':checked'))" {if $showCovers == false}checked="checked"{/if}></label>
			</form>


			{if $topRecommendations}
					{foreach from=$topRecommendations item="recommendations"}
							{include file=$recommendations}
					{/foreach}
			{/if}

			{if $recordCount}
				<div class="resulthead row">
					<div class="col-xs-12">
							{translate text="Showing"} <b>{$recordStart}</b> - <b>{$recordEnd}</b> {translate text='of'} <b>{$recordCount}</b>
							{if $debug}
								&nbsp;There are a total of {$favList->numValidListItems()} valid entries on the list.
							{/if}
					</div>

        {* Search Debugging *}
        {include file="Search/search-debug.tpl"}

				</div>
			{/if}

			{if $allowEdit && $userSort}
				<div class="alert alert-info alert-dismissible" role="alert">
					<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<strong>Drag-and-Drop!</strong> Just drag the list items into the order you like.
				</div>
			{/if}

			<div id="UserList">{*Keep only list entries in div for custom sorting functions*}
				{foreach from=$resourceList item=resource name="recordLoop" key=resourceId}
					<div class="result{if ($smarty.foreach.recordLoop.iteration % 2) == 0} alt{/if}">
						{* This is raw HTML -- do not escape it: *}

						{$resource}

					</div>
				{/foreach}
			</div>

{if $userSort}
				<script type="text/javascript">
					{literal}
					$(function(){
						$('#UserList').sortable({
							start: function(e,ui){
								$(ui.item).find('.related-manifestations').fadeOut()
							},
							stop: function(e,ui){
								$(ui.item).find('.related-manifestations').fadeIn()
							},
							update: function (e, ui){
								var updates = [],
										firstItemOnPage = {/literal}{$recordStart}{literal};
								$('#UserList>div>div>div>div').each(function(currentOrder){
									var id = this.id.replace('groupedRecord','') /* Grouped IDs for catalog items */
																	.replace('archive',''),      /*modified Islandora PIDs for archive items*/
													originalOrder = $(this).data('order'),
													change = currentOrder+firstItemOnPage-originalOrder,
													newOrder = originalOrder+change;
									if (change != 0) updates.push({'id':id, 'newOrder':newOrder});
								});
								$.getJSON('/MyAccount/AJAX',
												{
													method:'setListEntryPositions'
													,updates:updates
													,listID:{/literal}{$favList->id}{literal}
												}
												, function(response){
													if (response.success) {
														updates.forEach(function(e){
															if ($('#groupedRecord'+ e.id).length > 0) {
																$('#groupedRecord' + e.id).data('order', e.newOrder)
																				.find('span.result-index').text(e.newOrder + ')');
															/*$('#weight_'+ e.id).val(e.newOrder);*/
															} else if ($('#archive'+ e.id).length > 0) {
																$('#archive' + e.id).data('order', e.newOrder)
																				.find('span.result-index').text(e.newOrder + ')');
															/*$('#weight_'+ e.id).val(e.newOrder);*/
															}
														})
													}
												})
							}
						});
					});
					{/literal}
				</script>
			{/if}

			{if strlen($pageLinks.all) > 0}<div class="text-center">{$pageLinks.all}</div>{/if}
		{else}
				<div class="alert alert-warning">
						{translate text='You do not have any saved resources'}
				</div>
		{/if}
	{/if}
{else}
	<h3 id="listTitle">Invalid List</h3>
	<div class="alert alert-warning">
		Invalid List
	</div>
{/if}
{*{/strip}*}
