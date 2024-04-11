{strip}
<div class="navbar navbar-static-bottom">
	<div class="navbar-inner">
		<div class="row">
			<div class="col-tn-12 col-sm-4 text-right pull-right" id="connect-with-us-info" role="region" aria-label="Contact Information">
				{include file="contact-info.tpl"}
			</div>
			<div class="col-tn-12 col-sm-4 text-left pull-left" id="install-info">
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
			</div>
			<div class="col-tn-12 col-sm-4 text-center pull-left">
				<a href="http://lioninc.org" title="Member of Libraries Online Incorporated">
					<img src="/interface/themes/lion/images/lion_logo.png" alt="Member of Libraries Online Incorporated" style="max-width: 100%; max-height:130px; margin-left:20px; margin-right:20px">
				</a>
				{if $showPikaLogo}
					<a href="http://marmot.org/pika-discovery/about-pika" title="Proud Pika Partner">
						<img id="footer-pika-logo" src="{img filename='pika-logo.png'}" alt="Proud Pika Partner" style="max-width: 100%; max-height: 80px;">
					</a>
				{/if}
			</div>
		</div>
		{if $loggedIn && $userRoles && (in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles) || in_array('cataloging', $userRoles) || in_array('libraryManager', $userRoles) || in_array('locationManager', $userRoles))}
			<div class="row">
				<div class="col-sm-7 text-left" id="indexing-info">
					<small>Last Full Index {$lastFullReindexFinish}, Last Partial Index {$lastPartialReindexFinish}</small>
				</div>
			</div>
		{/if}
	</div>
</div>
{/strip}
