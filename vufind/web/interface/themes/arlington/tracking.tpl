{* Add Google Analytics*}

<!-- Respect browser do not track setting -->
<script>var dnt = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;</script>

{if $archivePage}
	{*
	* Archive specific tracking code
	* Track analytics for custom dimensions related to the Marmot Archive.
	* Send analytics to both archive GA account and library specific GA account
	*}
{literal}
	<!-- Google Analytics -->
<script>
	if (dnt != "1" && dnt != "yes") {
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
	}
</script>{/literal}
	<!-- End Google Analytics -->
{else}
	{if $googleAnalyticsId}
	{* OPAC and Library Specific tracking code *}
	{literal}
<script>
	function trackBrowseTitleClick(el) {
		if (dnt != "1" && dnt != "yes") {
			var cat    = $('.selected-browse-label-search-text').text();
			var subcat = $('.selected-browse-sub-category-label-search-text').text();
			var title = el.title;
			var browseCatString = cat;
			if(subcat != '') {
				browseCatString += '/' + subcat;
			}
			browseCatString += '/' + title;

			ga('opacTracker.send', 'event', {
				eventCategory: 'Browse Category',
				eventAction: 'click',
				eventLabel: browseCatString
			});{/literal}
			{if $googleAnalyticsLibraryId}{literal}
			ga('libraryTracker.send', 'event', {
				eventCategory: 'Browse Category',
				eventAction: 'click',
				eventLabel: browseCatString
			});{/literal}
			{/if}{literal}
		}
		// return true to bubble up through dom or next function
		return true;
	}

	function trackHoldTitleClick(workId) {
		if (dnt != "1" && dnt != "yes") {

			$.get('/API/WorkAPI?method=getBasicWorkInfo&id='+workId,
					function(data) {

						var title  = data.result.title;
						var format = data.result.format;
						var groupedWorkId = data.result.groupedWorkId
						var titleAndFormat = title + '/' + format;
						var titleAndGwId = title  + '/' + groupedWorkId;
						// Send title and format of hold
						ga('opacTracker.send', 'event', {
							eventCategory: 'Place Hold Title',
							eventAction: 'click',
							eventLabel: titleAndFormat,
						});
						// Send title and grouped work ID
						ga('opacTracker.send', 'event', {
							eventCategory: 'Place Hold Grouped Work',
							eventAction: 'click',
							eventLabel: titleAndGwId,
						});
							{/literal}
              {if $googleAnalyticsLibraryId}{literal}
						ga('libraryTracker.send', 'event', {
							eventCategory: 'Place Hold Title',
							eventAction: 'click',
							eventLabel: titleAndFormat,
						});
						ga('libraryTracker.send', 'event', {
							eventCategory: 'Place Hold Grouped Work',
							eventAction: 'click',
							eventLabel: titleAndGwId,
						});{/literal}
              {/if}{literal}
					});
		}
		// return true to bubble up through dom or next function
		return true;
	}

	if (dnt != "1" && dnt != "yes") {
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
				m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

		ga('create', '{/literal}{$googleAnalyticsId}{literal}', 'auto', 'opacTracker');{/literal}
		{if $googleAnalyticsLibraryId} {* Library tracking code *}{literal}
		ga('create', '{/literal}{$googleAnalyticsLibraryId}{literal}', 'auto', 'libraryTracker');
		ga('libraryTracker.set', 'dimension1', {/literal}'{$pType}'{literal}); // Patron Type
		ga('libraryTracker.set', 'dimension2', {/literal}'{$homeLibrary}'{literal}); // Home Library
		ga('libraryTracker.set', 'dimension3',{/literal}'{$physicalLocation}'{literal});{/literal}
		{/if}
		{literal}ga('opacTracker.set', 'dimension1', {/literal}'{$pType}'{literal}); // Patron Type
		ga('opacTracker.set', 'dimension2', {/literal}'{$homeLibrary}'{literal}); // Home Library
		ga('opacTracker.set', 'dimension3',{/literal}'{$physicalLocation}'{literal});
		ga('opacTracker.send', 'pageview');{/literal}
		{if $googleAnalyticsLibraryId}{literal}
		ga('libraryTracker.send', 'pageview');{/literal}
		{/if}{literal}


		ga('create', '{/literal}{$googleAnalyticsLinkingId}{literal}', 'auto', {'allowLinker': true});
		ga('require', 'linker');
		ga('linker:autoLink', ['library.arlingtonva.us', 'libsys.arlingtonva.us', 'm.libsys.arlingtonva.us', 'arlingtonva.libcal.com', 'library.arlingtonva.libguides.com', 'libraryarchives.arlingtonva.us', 'projectdaps.org', 'nauck.omeka.net'] );
		ga('send', 'pageview');
	}
</script>{/literal}
	{/if}
{/if}
