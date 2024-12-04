{if $cluster.showMoreFacetPopup}
	{foreach from=$cluster.list item=thisFacet name="narrowLoop"}
		{if $thisFacet.isApplied}
			<div class="facetValue"><img src="/images/silk/tick.png" alt="Selected"> {$thisFacet.display|escape} <a href="{$thisFacet.removalUrl|escape}" class="removeFacetLink">(remove)</a></div>
		{else}
			<div class="facetValue">{if $thisFacet.url !=null}<a href="{$thisFacet.url|escape}">{/if}{$thisFacet.display|escape}{if $thisFacet.url !=null}</a>{/if}{if $thisFacet.count != ''}&nbsp;({$thisFacet.count|number_format}){/if}</div>
		{/if}
	{/foreach}
	{* Show more list *}
	<div class="facetValue" id="more{$title}"><button class="btn btn-link" onclick="Pika.ResultsList.moreFacetPopup('More options for {$cluster.label|escape}', '{$title|escape}'); return false;">{translate text='more'} ...</button></div>
	<div id="moreFacetPopup_{$title}" style="display:none">
		<p>Please select one of the items below to narrow your search by {$cluster.label}.</p>
		{assign var="facetValueCount" value=$cluster.sortedList|@count}
		{if ($facetValueCount >= 100)} {* 100 is the default value for the facet limit. facets.ini setting facet_limit *}
		<div class="alert alert-warning">The top {$facetValueCount} items {*by count*} are shown. Other options will show as you narrow your search further.</div>
		{/if}
			<div class="row moreFacetPopup">
				{foreach from=$cluster.sortedList item=thisFacet name="narrowLoop"}
					<div>{if $thisFacet.url !=null}<a href="{$thisFacet.url|escape}">{/if}{$thisFacet.display|escape}{if $thisFacet.url !=null}</a>{/if}{if $thisFacet.count != ''}&nbsp;({$thisFacet.count|number_format}){/if}</div>
				{/foreach}
			</div>

	</div>
{else}
	{foreach from=$cluster.list item=thisFacet name="narrowLoop"}
		{if $smarty.foreach.narrowLoop.iteration == ($cluster.valuesToShow + 1)}
		{* Show More link*}
			<div class="facetValue" id="more{$title|escape}"><button class="btn btn-link" onclick="Pika.ResultsList.moreFacets('{$title|escape}'); return false;">{translate text='more'} ...</button></div>
		{* Start div for hidden content*}
			<div class="narrowGroupHidden" id="narrowGroupHidden_{$title}" style="display:none">
		{/if}
		{if $thisFacet.isApplied}
			<div class="facetValue"><img src="/images/silk/tick.png" alt="Selected"> {$thisFacet.display|escape} <a href="{$thisFacet.removalUrl|escape}" class="removeFacetLink">(remove)</a></div>
		{else}
			<div class="facetValue">{if $thisFacet.url !=null}<a href="{$thisFacet.url|escape}">{/if}{$thisFacet.display|escape}{if $thisFacet.url !=null}</a>{/if}{if $thisFacet.count != ''}&nbsp;({$thisFacet.count|number_format}){/if}</div>
		{/if}
	{/foreach}
	{if $smarty.foreach.narrowLoop.total > $cluster.valuesToShow}
		<div class="facetValue">
			<button class="btn btn-link" onclick="Pika.ResultsList.lessFacets('{$title|escape}'); return false;">{translate text='less'} ...</button>
		</div>
		</div>{* closes hidden div *}
	{/if}
{/if}