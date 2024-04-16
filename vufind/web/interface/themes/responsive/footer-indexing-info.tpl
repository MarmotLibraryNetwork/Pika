{strip}
	{if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('cataloging', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles))}
		<div class="row">
			<div class="col-sm-7 text-left" id="indexing-info">
				<small>Last Full Index {$lastFullReindexFinish}, Last Partial Index {$lastPartialReindexFinish}</small>
			</div>
		</div>
	{/if}
{/strip}