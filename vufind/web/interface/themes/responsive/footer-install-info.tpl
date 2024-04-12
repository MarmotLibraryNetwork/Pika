{strip}
	{if !$productionServer}
		<small class='location_info'>{$physicalLocation}{if $debug} ({$activeIp}){/if} - {$deviceName}</small>
	{/if}
	<small class='version_info'>{if !$productionServer} / {/if}v. {$gitBranch}{if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles))} ({$gitCommit}){/if}</small>
	{if $debug}
		<small class='session_info'> / session {$session}</small>
		<small class='session_info'> / Smarty v. {$smarty.version}</small>
		<small class='scope_info'> / scope {$solrScope}</small>
		{if (!empty($smarty.cookies.test_ip))}
			<small> / test_ip : {$smarty.cookies.test_ip}</small>
		{/if}
	{/if}
{/strip}