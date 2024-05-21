<form action="/MyAccount/MyLists" id="myListFormHead" class="form form-inline">
		<h1 role="heading" aria-level="1" class="h2" id="listsTitle">My Lists</h1>
	<div class="alert alert-info">
		For more information about User Lists, see the <a href="https://marmot-support.atlassian.net/l/c/NVtFyBaG">online documentation</a>.
	</div>
	<div id="listTopButtons" class="btn-toolbar">
		<div class="btn-group">
			<button onclick="return Pika.Account.showCreateListForm();" class="btn btn-sm btn-primary">Create a New List</button>
			{if $showConvertListsFromClassic}
				<button value="importFromClassic" class="btn btn-sm btn-default" onclick="return Pika.Lists.importListsFromClassic();">Import Lists from Classic</button>
			{/if}
		</div>
		<div class="btn-group pull-right">
			<button value="deleteSelected" class="btn btn-sm btn-danger" onclick="return Pika.Lists.deleteSelectedList();">Delete Selected Lists</button>
		</div>
	</div>
	<hr>
	<div class="row">
		<div class="form-group col-sm-4" id="sortOptions">
			<label for="sort" class="control-label">Sort Lists By&nbsp;</label>
			<select class="sortMethod form-control" id="sort" name="sort" onchange="Pika.Account.changeAccountSort($(this).val(), 'sort')">
					{foreach from=$sortOptions item=sortOptionLabel key=sortOption}
						<option value="{$sortOption}" {if $sortOption == $defaultSortOption}selected="selected"{/if}>{$sortOptionLabel}</option>
					{/foreach}
			</select>
		</div>
	</div>
	<hr>
		<input type="hidden" name="myListActionHead" id="myListActionHead" class="form">
		<input type="hidden" name="myListActionData" id="myListActionData" class="form">


		<label for="toggleSelectBoxes">
				<input type="checkbox" id="toggleSelectBoxes" name="toggleSelectBoxes" onclick="Pika.toggleCheckboxes('.myListsCheckBoxes', '#toggleSelectBoxes');">
				<strong>Select All</strong>
		</label>

		{foreach from=$myLists item=myList name=myLists}
			{if $myList.id != -1}
				<div class="result">

					<div class="row result-title-row">
						<div class="col-tn-12">
							<h2 class="h3">
								<span class="result-index">{$smarty.foreach.myLists.iteration}.</span>&nbsp;
								<a href="{$myList.url}" class="result-title{* notranslate*}">{$myList.name}</a>
							</h2>
						</div>
					</div>

					<div class="row">
						<div class="col-md-1">
							<input type="checkbox" class="form-control-static myListsCheckBoxes" value="{$myList.id}" aria-label="select list to delete">
						</div>
						<div class="col-md-2">
							<img src="/bookcover.php?id={$myList.id}&size=medium&type=userList" alt="Cover Image for list &quot;{$myList.name}&quot;">
						</div>
						<div class="col-md-9">
							<div class="row">
								<div class="col-xs-10 col-lg-11">
									<div class="row related-manifestation">
										<div class="col-tn-4">
											<span class="result-label">Items:</span>
											<span class="result-value">{if $myList.numTitles}{$myList.numTitles}{else}0{/if}</span>
										</div>
										<div class="col-tn-4">
											<span class="result-label">List Access:</span>
											<span class="result-value">{if $myList.isPublic}Public{else}Private{/if}</span>
										</div>
										<div class="col-tn-4">
											<span class="result-label">Default Sort:</span>
											<span class="result-value">{$myList.defaultSort}</span>
										</div>
									</div>
									<div class="row related-manifestation">
										<div class="col-sm-6">
											<span class="result-label">Created:</span>
											<span class="result-value">{$myList.created|date_format:"%b %d, %Y %r"}</span>
										</div>
										<div class="col-sm-6">
											<span class="result-label">Last Updated:</span>
											<span class="result-value">{$myList.dateUpdated|date_format:"%b %d, %Y %r"}</span>
										</div>

									</div>
									<div class="row">
										<div class="col-tn-12">
											{if $myList.description}{$myList.description}{/if}
										</div>
									</div>
									<div class="row">
										<div class="col-tn-12">

											{if $myList.isPublic}
												<div class="result-tools-horizontal btn-toolbar">

													<div class="btn-group btn-group-sm">
														<div class="share-tools">
															<span id="share-list-tools-label-{$myList.id}" class="share-tools-label hidden-inline-xs">SHARE LIST</span>
															<ul aria-labelledby="share-list-tools-label-{$myList.id}" class="share-tools-list list-inline" style="display: inline">
																<li>
																	<a href="#" onclick="return Pika.Lists.emailListAction({$myList.id})" title="share via e-mail">
																<img src="{img filename='email-icon.png'}" alt="E-mail this" style="cursor:pointer;">
															</a>
																</li>
																<li>
																	<a href="#" onclick="return Pika.Lists.exportListFromLists('{$myList.id}');" title="Export List to Excel">
																<img src="{img filename='excel.png'}" alt="Export to Excel">
															</a>
																</li>
																<li>
																	<a href="https://twitter.com/compose/tweet?text={$myList.name|escape:"html"}+{$url|escape:"html"}/MyAccount/MyList/{$myList.id}"
																 target="_blank" title="Share on Twitter">
																<img src="{img filename='twitter-icon.png'}" alt="Share on Twitter">
															</a>
																</li>
																<li>
																	<a href="http://www.facebook.com/sharer/sharer.php?u={$url|escape:"html"}/MyAccount/MyList/{$myList.id}"
																 target="_blank" title="Share on Facebook">
																<img src="{img filename='facebook-icon.png'}" alt="Share on Facebook">
															</a>
																</li>
																<li>
																	{include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|cat:"/MyAccount/MyList/"|cat:$myList.id description="See My List "|cat:$myList.name|cat:" at $homeLibrary" coverUrl=$url|cat:"/bookcover.php?id="|cat:$myList.id|cat:"&size=medium&type=userList"}
																</li>
															</ul>
														</div>
													</div>
												</div>
											{else}
												<div class="btn-group btn-group-sm">
													<button value="emailList" class="btn btn-sm btn-default"
																	onclick='return Pika.Lists.emailListAction("{$myList.id}")'
																	title="Share via e-mail">Email List
													</button>
													<button value="exportToExcel" class="btn btn-sm btn-default"
																	onclick="return Pika.Lists.exportListFromLists('{$myList.id}');">Export to Excel
													</button>
												</div>
											{/if}

										</div>
									</div>
								</div>
								<div class="col-xs-2 col-sm-2 col-md-2 col-lg-1">
									{if $staff && $myList.isPublic}
										<div class="btn-group-vertical">
											<button value="transferList" onclick="return Pika.Lists.transferListToUser({$myList.id}); "
															class="btn btn-danger">Transfer
											</button>
										</div>
									{/if}
								</div>
							</div>
						</div>
					</div>
				</div>
			{/if}
		{/foreach}
{literal}
	<script>
		$(document).ready(function(){
			let myListActionData = $("#myListActionData");
			$(".myListsCheckBoxes").click(function(data){
				if (this.checked) {
					myListActionData.val(myListActionData.val() + this.value + ",");
				} else {
					var str = myListActionData.val();
					var newStr = str.replace(this.value + ",", "");
					myListActionData.val(newStr);
				}
			});
			$("#toggleSelectBoxes").click(function (data) {
				if (this.checked) {
					var ids = Array();
					$(".myListsCheckBoxes").each(function () {
						ids.push(this.value.replace(",", ""));
					});
					var stringId = ids.join();
					myListActionData.val(stringId + ",");
				} else {
					myListActionData.val("");
				}
			});
		});
	</script>
	{/literal}
</form>
