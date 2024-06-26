{* Add Google Analytics 4 *}

<!-- Respect browser do not track setting -->
<script>var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;</script>
{if $archivePage}
    {*
		* Archive specific tracking code
		* Track analytics for custom dimensions related to the Marmot Archive.
		* Send analytics to both archive GA account and library specific GA account
		*}
    {if $googleAnalytics4Id}
    {literal}
			<!-- GA4 -->
			<!-- Google tag (gtag.js) -->
			<script async src="https://www.googletagmanager.com/gtag/js?id={/literal}{$googleAnalytics4Id}{literal}"></script>
			<script>
				if (dnt != "1" && dnt != "yes") {
					window.dataLayer = window.dataLayer || [];
					function gtag(){dataLayer.push(arguments);}
					gtag('js', new Date());
					gtag('config', '{/literal}{$googleAnalytics4Id}{literal}');
				}
			</script><!-- End GA4 -->{/literal}

    {/if}
	<!-- End Google Analytics for Archive-->
{else}
    {if $googleAnalytics4Id}
    {literal}
			<!-- GA4 -->
			<!-- Google tag (gtag.js) -->
			<script async src="https://www.googletagmanager.com/gtag/js?id={/literal}{$googleAnalytics4Id}{literal}"></script>
			<script>
			if (dnt != "1" && dnt != "yes") {
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
				gtag('config', '{/literal}{$googleAnalytics4Id}{literal}');
				gtag('event', 'pika_pageview', {
					'patron_home_library': {/literal}'{$homeLibrary}'{literal},
					'patron_location': {/literal}'{$physicalLocation}'{literal},
					'patron_type': {/literal}'{$pType}'{literal}
				});
				gtag('config', {/literal}'{$googleAnalytics4Id}'{literal},{ 'debug_mode':true });
				}

</script><!-- End GA4 -->{/literal}
    {/if}
{/if}