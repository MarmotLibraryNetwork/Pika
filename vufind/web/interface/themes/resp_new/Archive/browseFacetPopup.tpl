{strip}
	<div id="moreFacetPopup">
		<p>Please select one of the items below to narrow your search by.</p>
		{if count($facetValues) >= 120}
			<div class="alert alert-info">Only the top 120 values are shown.  Additional values may be available by searching.</div>
		{/if}
		<div class="container-12">
			<div class="row moreFacetPopup">
				{foreach from=$facetValues item=thisFacet name="narrowLoop"}
					<div>{if $thisFacet.url !=null}<a href="{$thisFacet.url|escape}">{/if}{$thisFacet.display|escape}{if $thisFacet.url !=null}</a>{/if}{if $thisFacet.count != ''}&nbsp;({$thisFacet.count|number_format}){/if}</div>
				{/foreach}
			</div>
		</div>
	</div>
{/strip}