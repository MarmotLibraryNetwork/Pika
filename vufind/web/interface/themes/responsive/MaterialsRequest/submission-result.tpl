<div id="page-content" class="content">
	<div id="main-content">
		<h1 role="heading" aria-level="1" class="h2">{translate text='Materials_Request_alt'} Result</h1>
		{if $success == 0}
			<div class="alert alert-danger">
			{$error}
			</div>
		{else}
			<div class="result">
				<div class="alert alert-success">
					Your request for <b>{$materialsRequest->title}</b> by <b>{$materialsRequest->author}</b> was submitted successfully.
				</div>
				<div id="materialsRequestSummary" class="alert alert-info">
					You have used <strong>{$requestsThisYear}</strong> of your {$maxRequestsPerYear} yearly {translate text='materials request'}s.  We also limit patrons to {$maxActiveRequests} active {translate text='materials_request_short'}s at a time.  You currently have <strong>{$openRequests}</strong> active {translate text='materials_request_short'}s.
				</div>

				<p>
					<a role="button" class="btn btn-primary" href="{$accountPageLink}">See My {translate text='Materials_Request_alt'}s</a>
				</p>
			</div>
		{/if}
	</div>
</div>
