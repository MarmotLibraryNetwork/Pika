{strip}
<div class="navbar navbar-static-bottom">
	<div class="navbar-inner">
		<div class="row">
			<div class="col-tn-12 col-sm-4 text-right pull-right" id="connect-with-us-info">
				{if $twitterLink || $facebookLink || $generalContactLink || $youtubeLink || $instagramLink || $goodreadsLink}
					<span id="connect-with-us-label" class="large">CONNECT WITH US</span>
					{if $twitterLink}
						<a href="{$twitterLink}" class="connect-icon"><img src="{img filename='twitter.png'}" class="img-rounded"></a>
					{/if}
					{if $facebookLink}
						<a href="{$facebookLink}" class="connect-icon"><img src="{img filename='facebook.png'}" class="img-rounded"></a>
					{/if}
					{if $youtubeLink}
						<a href="{$youtubeLink}" class="connect-icon"><img src="{img filename='youtube.png'}" class="img-rounded"></a>
					{/if}
					{if $instagramLink}
						<a href="{$instagramLink}" class="connect-icon"><img src="{img filename='instagram.png'}" class="img-rounded"></a>
					{/if}
					{if $goodreadsLink}
						<a href="{$goodreadsLink}" class="connect-icon"><img src="{img filename='goodreads.png'}" class="img-rounded"></a>
					{/if}
					{if $generalContactLink}
						<a href="{$generalContactLink}" class="connect-icon"><img src="{img filename='email-contact.png'}" class="img-rounded"></a>
					{/if}
				{/if}
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
					<img src="/interface/themes/lion/images/lion_logo.png" alt="Member of Libraries Online Incorporated" style="max-width: 100%; max-height:140px; margin-left:20px; margin-right:20px">
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
