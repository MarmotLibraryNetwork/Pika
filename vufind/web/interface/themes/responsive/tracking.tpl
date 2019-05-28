{* Add Google Analytics*}

{if $archivePage}
	{*
	* Archive specific tracking code
	* Track analytics for custom dimensions related to the Marmot Archive.
	* Send analytics to both archive GA account and library specific GA account
	*}
{literal}
	<!-- Google Analytics -->
	<script>
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
				m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

		ga('create', 'UA-55801358-12', 'auto', 'archiveTracker');
		ga('archiveTracker.set', 'dimension1', '{/literal}{$lid}{literal}');{/literal}
		{if $googleAnalyticsId}{literal}
		ga('create', '{/literal}{$googleAnalyticsId}{literal}', 'auto', 'opacTracker');
		ga('opacTracker.send', 'pageview');
		{/literal}
		{/if}
		{literal}
		ga('archiveTracker.send', 'pageview');
	</script>{/literal}
	<!-- End Google Analytics -->
{else}
	{if $googleAnalyticsId}
	{literal}
		<script type="text/javascript">
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', '{/literal}{$googleAnalyticsId}{literal}']);
			_gaq.push(['_setCustomVar', 1, 'theme', {/literal}'{$primaryTheme}'{literal}, '2']);
			_gaq.push(['_setCustomVar', 2, 'mobile', {/literal}'{$isMobile}'{literal}, '2']);
			_gaq.push(['_setCustomVar', 3, 'physicalLocation', {/literal}'{$physicalLocation}'{literal}, '2']);
			_gaq.push(['_setCustomVar', 4, 'pType', {/literal}'{$pType}'{literal}, '2']);
			_gaq.push(['_setCustomVar', 5, 'homeLibrary', {/literal}'{$homeLibrary}'{literal}, '2']);
			_gaq.push(['_setDomainName', {/literal}'{$googleAnalyticsDomainName}'{literal}]);
			_gaq.push(['_trackPageview']);
			_gaq.push(['_trackPageLoadTime']);

			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();

		</script>
	{/literal}
	{/if}

{/if}