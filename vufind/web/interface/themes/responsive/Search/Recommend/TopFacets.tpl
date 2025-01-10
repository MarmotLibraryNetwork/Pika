{strip}

{if $topFacetSet}
<div class="topFacets">
	<br>
	{foreach from=$topFacetSet item=cluster key=title}
		{if stripos($title, 'format_category') !== false}
			{if ($categorySelected == false)}
				<div class="formatCategories top-facet" id="formatCategories">
					<ul id="categoryValues" class="row list-unstyled" aria-label="{$cluster.label}">
						{foreach from=$cluster.list item=thisFacet name="narrowLoop" key="i"}
							{if $thisFacet.isApplied}
								<li class="categoryValue categoryValue_{translate text=$thisFacet.value|lower|replace:' ':''} col-tn-2{if $thisFacet.value=="Books"}{* Add offset to first column *} col-tn-offset-1{/if}">
									<a href="{$thisFacet.removalUrl|escape}" class="removeFacetLink" title="Remove Filter">
										<div class="row">
											<div class="col-xs-6">
												<img src="{img filename=$thisFacet.imageNameSelected}" alt="{translate text=$thisFacet.value|escape} selected">
											</div>
											<div class="col-xs-6 formatCategoryLabel">
												{$thisFacet.value|escape}
												<br>(Remove)
											</div>
										</div>
									</a>
								</li>
							{else}
								<li class="categoryValue categoryValue_{translate text=$thisFacet.value|lower|replace:' ':''} col-tn-2{if $thisFacet.value=="Books" && count($cluster.list) < 6}{* Add offset to first column *} col-tn-offset-1{/if}">
									<a href="{$thisFacet.url|escape}">
										<div class="row">
											<div class="col-xs-6">
												<img src="{img filename=$thisFacet.imageName}" alt="{translate text=$thisFacet.value|escape}">
											</div>
											<div class="col-xs-6 formatCategoryLabel">
												{translate text=$thisFacet.value|escape}<br>({$thisFacet.count|number_format:0:".":","})
											</div>
										</div>
									</a>
								</li>
							{/if}
						{/foreach}
					</ul>
					<div class="clearfix"></div>
				</div>
			{/if}
		{elseif stripos($title, 'availability_toggle') !== false}
			<div id="availabilityControlContainer" class="row text-center top-facet">
				<div class="col-tn-12">
					<div id="availabilityControl" class="btn-group" data-toggle="buttons-radio" aria-label="{$cluster.label}">
						{foreach from=$cluster.list item=thisFacet name="narrowLoop"}
							{if $thisFacet.isApplied}
								<button type="button" id="{$thisFacet.value|escape|regex_replace:'/[()\s]/':''}" aria-pressed="true" class="btn btn-primary" name="availabilityControls">{$thisFacet.value|escape}{if $thisFacet.count > 0} ({$thisFacet.count|number_format:0:".":","}){/if}</button>
							{else}
								<button type="button" id="{$thisFacet.value|escape|regex_replace:'/[()\s]/':''}" class="btn btn-default" name="availabilityControls" data-url="{$thisFacet.url|escape}" onclick="window.location = $(this).data('url')" >{$thisFacet.value|escape}{if $thisFacet.count > 0} ({$thisFacet.count|number_format:0:".":","}){/if}</button>
							{/if}
						{/foreach}
					</div>
				</div>
			</div>
		{else}
			{*TODO: Rebuild to use the responsive framework and get rid of the html table *}
			<div class="authorbox top-facet">
				<h5>{translate text=$cluster.label}<span>{translate text="top_facet_suffix"}</span></h5>
				<table class="facetsTop navmenu narrow_begin">
					{foreach from=$cluster.list item=thisFacet name="narrowLoop"}
						{if $smarty.foreach.narrowLoop.iteration == ($topFacetSettings.rows * $topFacetSettings.cols) + 1}
							<tr id="more{$title}"><td><a href="#" onclick="moreFacets('{$title}'); return false;">{translate text='more'} ...</a></td></tr>
							</table>
							<table class="facetsTop navmenu narrowGroupHidden" id="narrowGroupHidden_{$title}">
							<tr><th colspan="{$topFacetSettings.cols}"><div class="top_facet_additional_text">{translate text="top_facet_additional_prefix"}{translate text=$cluster.label}<span>{translate text="top_facet_suffix"}</span></div></th></tr>
						{/if}
						{if $smarty.foreach.narrowLoop.iteration % $topFacetSettings.cols == 1}
							<tr>
						{/if}
						{if $thisFacet.isApplied}
							<td>{$thisFacet.value|escape} <img src="/images/silk/tick.png" alt="Selected"> <a href="{$thisFacet.removalUrl|escape}" class="removeFacetLink">(remove)</a></td>
						{else}
							<td><a href="{$thisFacet.url|escape}">{$thisFacet.value|escape}</a> ({$thisFacet.count})</td>
						{/if}
						{if $smarty.foreach.narrowLoop.iteration % $topFacetSettings.cols == 0 || $smarty.foreach.narrowLoop.last}
							</tr>
						{/if}
						{if $smarty.foreach.narrowLoop.total > ($topFacetSettings.rows * $topFacetSettings.cols) && $smarty.foreach.narrowLoop.last}
							<tr><td><a href="#" onclick="lessFacets('{$title}'); return false;">{translate text='less'} ...</a></td></tr>
						{/if}
					{/foreach}
				</table>
			</div>
		{/if}
	{/foreach}
	</div>
{else}
	<br>
{/if}
{/strip}
