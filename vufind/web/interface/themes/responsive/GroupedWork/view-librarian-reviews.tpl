{strip}
	{foreach from=$librarianReviews item=librarianReview}
		<div class="review">
		{if $librarianReview->title}
			<h3 class="reviewSource h4">{$librarianReview->title}</h3>
		{/if}
			<div>
				<p class="reviewContent">{$librarianReview->review}</p>
				<div class="reviewCopyright"><small>{$librarianReview->source}</small></div>
			</div>
    {if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles) || in_array('contentEditor', $userRoles))}
			<div>
				<a class="btn btn-sm btn-default" href="/Admin/LibrarianReviews?objectAction=edit&id={$librarianReview->id}">Edit Librarian Review</a>
			</div>
    {/if}
		</div>
	{foreachelse}
		<p>No reviews currently exist.</p>
	{/foreach}
{/strip}