{strip}
	<div id="main-content" class="col-md-12">
		<h2>Editorial Review: {$editorialReview->title}</h2>
		{if $loggedIn && $userRoles && (in_array('libraryAdmin', $userRoles) || in_array('opacAdmin', $userRoles) || in_array('contentEditor', $userRoles))}
		<div class="btn-group btn-group-sm">
			<div class='btn btn-sm btn-default'><a href='/EditorialReview/{$editorialReview->editorialReviewId}/Edit'>Edit</a></div>
			{if in_array('opacAdmin', $userRoles)}
			<div class='btn btn-sm btn-danger'><a href='/EditorialReview/{$editorialReview->editorialReviewId}/Delete' onclick="return confirm('Are you sure you want to delete this Editorial Review?');">Delete</a></div>
			{/if}
		</div>
		{/if}

		<div class='row'>
			<div class="result-label col-md-3">Title: </div>
			<div class="col-md-9 result-value">{$editorialReview->title}</div>
		</div>
		<div class='row'>
			<div class="result-label col-md-3">Teaser: </div>
			<div class="col-md-9 result-value">{$editorialReview->teaser}</div>
		</div>
		<div class='row'>
			<div class="result-label col-md-3">Review: </div>
			<div class="col-md-9 result-value">{$editorialReview->review}</div>
		</div>
		<div class='row'>
			<div class="result-label col-md-3">Source: </div>
			<div class="col-md-9 result-value">{$editorialReview->source}</div>
		</div>
		<div class='row'>
			<div class="result-label col-md-3">Record Id: </div>
			<div class="col-md-9 result-value"><a href='/GroupedWork/{$editorialReview->recordId}/Home'>{$editorialReview->recordId}</a></div>
		</div>
		<div class='row'>
			<div class="result-label col-md-3">Tab Name: </div>
			<div class="col-md-9 result-value">{$editorialReview->tabName}</div>
		</div>
	</div>
{/strip}