{*{if (isset($title)) }*}
	{*<script type="text/javascript">*}
		{*alert("{$title}");*}
	{*</script>*}
{*{/if}*}
{if $loggedIn}

	{include file="MyAccount/patronWebNotes.tpl"}

	{* Alternate Mobile MyAccount Menu *}
	{include file="MyAccount/mobilePageHeader.tpl"}

	<span class='availableHoldsNoticePlaceHolder'></span>

	<div class="resulthead">
		<h3>{translate text='My Ratings'}</h3>

		<div class="page">
			{if $ratings}
				<table class="table table-striped" id="myRatingsTable">
					<thead>
						<tr>
							<th>{translate text='Date'}</th>
							<th>{translate text='Title'}</th>
							<th>{translate text='Author'}</th>
							<th>{translate text='Rating'}</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>

						{foreach from=$ratings name="recordLoop" key=recordKey item=rating}

							<tr id="myRating{$rating.groupedWorkId|escape}" class="result {if ($smarty.foreach.recordLoop.iteration % 2) == 0}alt{/if} record{$smarty.foreach.recordLoop.iteration}">
								<td>
									{if isset($rating.dateRated)}
										<span data-date="{$rating.dateRated}">{$rating.dateRated|date_format}</span>
									{/if}
								</td>
								<td class="myAccountCell">
									<a href='{$rating.link}'>{$rating.title}</a>
								</td>
								<td class="myAccountCell">
									{$rating.author}
								</td>
								<td class="myAccountCell">
									{*{include file='GroupedWork/title-rating.tpl' shortId=$rating.shortId recordId=$rating.fullId ratingData=$rating.ratingData}*}
									{include file='GroupedWork/title-rating.tpl' id=$rating.groupedWorkId ratingData=$rating.ratingData}
									<p>{$rating.review}</p>
								</td>
								<td class="myAccountCell">
									<span class="btn btn-xs btn-warning" onclick="return Pika.GroupedWork.clearUserRating('{$rating.groupedWorkId}');">{translate text="Clear"}</span>
								</td>
							</tr>
						{/foreach}
						</tbody>
					</table>
			{if count($ratings) > 5}
				<script type="text/javascript">
					{literal}
					$.fn.dataTable.ext.order['dom-rating'] = function  ( settings, col )
					{
						return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {

							return $('.title-rating', td).attr("data-user_rating") * 1;
						} );
					}
					$.fn.dataTable.ext.order['dom-date'] = function (settings, col){
						return this.api().column(col, {order:'index'}).nodes().map(function (td, i){

							return $('span', td).attr("data-date");
						});
					}
					$(document).ready(function(){
						$('#myRatingsTable').DataTable({
							"columns":[
											{"orderDataType": "dom-date"},
											null,
											null,
											{"orderDataType": "dom-rating"},
											{"orderable": false}

							],
							pageLength: 10,
							"order": [[0, "desc"]]

						});
					})
					{/literal}
				</script>
			{/if}
				{else}
					<div class="alert alert-info">You have not rated any titles yet.</div>
				{/if}

			{if $notInterested}
				<h3>{translate text='Not Interested'}</h3>
				<table class="myAccountTable table stripe" id="notInterestedTable">
					<thead>
						<tr>
							<th>Date</th>
							<th>Title</th>
							<th>Author</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$notInterested item=notInterestedTitle}
							<tr id="notInterested{$notInterestedTitle.id}">
								<td>{$notInterestedTitle.dateMarked|date_format}</td>
								<td><a href="{$notInterestedTitle.link}">{$notInterestedTitle.title}</a></td>
								<td>{$notInterestedTitle.author}</td>
								<td><span class="btn btn-xs btn-warning" onclick="return Pika.GroupedWork.clearNotInterested('{$notInterestedTitle.id}');">Clear</span></td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if count($notInterested) > 5}
					<script type="text/javascript">
						{literal}
						$(document).ready(function(){
							$('#notInterestedTable').DataTable({
								"order": [[0, "asc"]],
								pageLength: 25
							});
						})
						{/literal}
					</script>
				{/if}
			{/if}
			</div>
	</div>
{else}
	 {include file="MyAccount/loginRequired.tpl"}
{/if}
