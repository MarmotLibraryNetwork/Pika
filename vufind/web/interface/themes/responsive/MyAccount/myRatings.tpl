{if $loggedIn}

	{include file="MyAccount/patronWebNotes.tpl"}

	{* Alternate Mobile MyAccount Menu *}
	{include file="MyAccount/mobilePageHeader.tpl"}

	<span class='availableHoldsNoticePlaceHolder'></span>

	<div class="resulthead">
		<h1 role="heading" aria-level="1" class="h2">{translate text='My Ratings'}</h1>

		<br>

		<div class="page">
			{if $ratings}
				<table class="table table-striped" id="myRatingsTable">
					<thead>
						<tr>
							<th>{translate text='Date'}</th>
							<th>{translate text='Title'}</th>
							<th>{translate text='Author'}</th>
							<th aria-label="{translate text='Rating'} column">{translate text='Rating'}</th>
							{*<th>&nbsp;</th>*}
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
									{* include file='GroupedWork/title-rating.tpl' id=$rating.groupedWorkId ratingData=$rating.ratingData *}
									{include file='MyAccount/star-rating.tpl' id=$rating.groupedWorkId ratingData=$rating.ratingData ratingTitle=$rating.title}
									<p>{$rating.review}</p>
								</td>
							</tr>
						{/foreach}
						</tbody>
					</table>
				<script>
					{literal}
					/*
					New star ratings 5-2024
					This is vanilla js
					*/
					document.querySelectorAll('.star_rating').forEach(function(form) {
						var radios = form.querySelectorAll('input[type=radio]');
						//var btn = form.querySelector('button');
						var output = form.querySelector('output');

						var submit_rating = function (star_rating, rating_text) {
							//alert('Grouped Work ID: ' + grouped_work_id + ', Star Rating: ' + star_rating);
							let grouped_work_id = form.querySelector('[name="grouped-work-id"]').value;

							// Create a new FormData object to hold the form data
							let formData = new FormData();
							formData.append('method', 'RateTitle');
							formData.append('grouped-work-id', grouped_work_id);
							formData.append('rating', star_rating);

							// Build the XHR url
							let protocol = window.location.protocol;
							let hostname = window.location.hostname;
							let xhr_url = protocol + "//" + hostname + "/GroupedWork/" + encodeURIComponent(grouped_work_id) + "/AJAX?" +
											"method=RateTitle" +
											"&id=" + encodeURIComponent(grouped_work_id) +
											"&rating=" + encodeURIComponent(star_rating);
							// console.log(xhr_url);
							// Create a new XMLHttpRequest object
							var xhr = new XMLHttpRequest();
							xhr.open('GET', xhr_url, true);

							// Set up a handler for when the request finishes
							xhr.onload = function () {
								if (xhr.status === 200) {
									// Successfully received response
									alert('Rating submitted successfully');
								} else {
									// There was a problem with the request
									alert('Error submitting rating');
								}
							};

							// Send the form data
							xhr.send(formData);

							// Update the output with the rating text
							output.textContent = rating_text;
						};

						Array.prototype.forEach.call(radios, function (el) {
							var label = el.nextSibling.nextSibling;

							label.addEventListener("click", function () {
								let star_rating = el.value;
								//var rating_text = label.querySelector('span').textContent;
								let rating_title = el.closest('div').getAttribute('data-rating_title');
								let rating_text = rating_title + " rated " + star_rating + " star";
								if (star_rating != 1) {
									star_rating += "s";
								}

								submit_rating(star_rating, rating_text);
							});
						});

						form.addEventListener('submit', function (event) {
							let star_rating = form.querySelector(':checked').value;
							//var rating_text = form.querySelector(':checked ~ label span').textContent;
							let rating_title = form.closest("div").getAttribute('data-rating_title');
							let rating_text = rating_title + " rated " + star_rating + " star";
							if (star_rating != 1) {
								star_rating += "s";
							}
							submit_rating(star_rating, rating_text);
							event.preventDefault();
							event.stopImmediatePropagation();
						});
					});
					{/literal}
				</script>
			{if count($ratings) > 5}
				<script>
					{literal}
					$.fn.dataTable.ext.order['dom-rating'] = function (settings, col){
						return this.api().column(col, {order: 'index'}).nodes().map(function (td, i){
							return $('.title-rating', td).attr("data-user_rating");
						});
					}
					$.fn.dataTable.ext.order['dom-date'] = function (settings, col){
						return this.api().column(col, {order: 'index'}).nodes().map(function (td, i){
							return $('span', td).attr("data-date");
						});
					}
					$(document).ready(function(){
						$('#myRatingsTable').DataTable({
							"columns":[
											{"orderDataType": "dom-date"},
											null,
											null,
											{"orderDataType": "dom-rating"}
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
				<h2 class="h3">{translate text='Not Interested'}</h2>
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
								<td><span data-date="{$notInterestedTitle.dateMarked}">{$notInterestedTitle.dateMarked|date_format}</span></td>
								<td><a href="{$notInterestedTitle.link}">{$notInterestedTitle.title}</a></td>
								<td>{$notInterestedTitle.author}</td>
								<td><span class="btn btn-xs btn-warning" onclick="return Pika.GroupedWork.clearNotInterested('{$notInterestedTitle.id}');">Clear</span></td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				{if count($notInterested) > 5}
				<script>
					{literal}

					$.fn.dataTable.ext.order['dom-ni-date'] = function (settings, col){
						return this.api().column(col, {order:'index'}).nodes().map(function (td, i){
							return $('span', td).attr("data-date");
						});
					}
					$(document).ready(function(){
						$('#notInterestedTable').DataTable({
							"columns":[
								{"orderDataType": "dom-ni-date"},
								null,
								null,
								{"orderable": false}

							],
							pageLength: 10,
							"order": [[0, "desc"]]

						});
					});

					{/literal}
				</script>
				{/if}
			{/if}
			</div>
	</div>
{else}
	 {include file="MyAccount/loginRequired.tpl"}
{/if}
<!-- star ratings - pull and put in ratings.less file once approved. -->
<style>
	{literal}

	.star_rating svg {
		width: 18px;
		height: 18px;
		fill: currentColor;
		stroke: currentColor;

	}

	.star_rating label,
	.star_rating output {
		box-sizing: content-box;
		line-height: normal;
		display: block;
		float: left;
		font-size: 18px;
		font-weight: normal;
		height: 22px;
		color: #d6430a;
		cursor: pointer;
		/* Transparent border avoids jumping when a colored border is applied on :hover/:focus */
		border: 2px solid transparent;

	}

	.star_rating output {
		font-size: 18px;
		padding: 0 18px;
	}

	.star_rating input:checked~label {
		color: #999;
	}

	.star_rating input:checked+label {
		color: #d6430a;
		border-bottom-color: #d6430a;
	}

	.star_rating input:focus+label {
		border: #E15F15 solid 2px;
	}

	.star_rating:hover input+label {
		color: #d6430a;
	}

	.star_rating input:hover~label,
	.star_rating input:focus~label,
	.star_rating input[class="star0"]+label {
		color: #999;
	}

	.star_rating input:hover+label,
	.star_rating input:focus+label {
		color: #d6430a;
	}

	.star_rating input[class="star0"]:checked+label {
		color: #d6430a;
	}

	.star_rating [type="submit"] {
		float: none;
	}

	.visuallyhidden {
		border: 0;
		clip: rect(0 0 0 0);
		-webkit-clip-path: inset(50%);
		clip-path: inset(50%);
		height: 1px;
		margin: -1px;
		overflow: hidden;
		padding: 0;
		position: absolute;
		width: 1px;
		white-space: nowrap;

	{/literal}
</style>