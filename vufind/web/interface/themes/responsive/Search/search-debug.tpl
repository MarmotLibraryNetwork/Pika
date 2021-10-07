{strip}
    {if $solrSearchDebug}
			<div id="solrSearchOptionsToggle" onclick="$('#solrSearchOptions').toggle()">Show Search Options</div>
			<div id="solrSearchOptions" style="display:none">
				<pre>Search options: {$solrSearchDebug}</pre>
			</div>
    {/if}

    {if $solrLinkDebug}
			<div id="solrLinkToggle" onclick="$('#solrLink').toggle()">Show Solr Link</div>
			<div id="solrLink" style="display:none">
				<pre>{$solrLinkDebug}</pre>
			</div>
    {/if}

    {if $debugTiming}
			<div id="solrTimingToggle" onclick="$('#solrTiming').toggle()">Show Solr Timing</div>
			<div id="solrTiming" style="display:none">
				<pre>{$debugTiming}</pre>
			</div>
    {/if}
{/strip}