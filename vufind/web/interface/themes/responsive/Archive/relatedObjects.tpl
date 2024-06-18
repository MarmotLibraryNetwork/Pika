{strip}
	{if ($displayType == 'map' || $displayType == 'mapNoTimeline' || $displayType == 'timeline' || $displayType == 'scroller' || $displayType == 'basic') && $page == 1 && $reloadHeader == 1}
		<div id="exhibit-results-loading" class="row" style="display: none">
			<div class="alert alert-info">
				Updating results, please wait.
			</div>
		</div>

		<div class="row">
			<div class="col-sm-6">
				{if ($displayType == 'map' || $displayType == 'mapNoTimeline' || $displayType == 'timeline')}
					<form action="/Archive/Results">
						<div class="input-group">
							<input type="text" name="lookfor" size="30" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="Search this collection" aria-label="Search this collection">
							<span class="input-group-btn" id="search-actions">
								<button class="btn btn-primary" type="submit">GO</button>
							</span>
							<input type="hidden" name="islandoraType" value="IslandoraKeyword">
							<input type="hidden" name="filter[]" value='RELS_EXT_isMemberOfCollection_uri_ms:"info:fedora/{$exhibitPid}"'>
						</div>
					</form>
				{elseif $displayType == 'basic'}
					<form action="/Archive/Results">
						<div class="input-group">
							<input type="text" name="lookfor" size="30" title="Enter one or more terms to search for.	Surrounding a term with quotes will limit result to only those that exactly match the term." autocomplete="off" class="form-control" placeholder="Search this collection" aria-label="Search this collection">
							<span class="input-group-btn" id="search-actions">
								<button class="btn btn-primary" type="submit">GO</button>
							</span>
							<input type="hidden" name="islandoraType" value="IslandoraKeyword">
							<input type="hidden" name="filter[]" value='ancestors_ms:"{$exhibitPid}"'>
						</div>
					</form>
				{/if}
			</div>
			<div class="col-sm-5 col-sm-offset-1">
				{* Display information to sort the results (by date or by title *}
				<div class="input-group">
					<label for="results-sort" class="input-group-addon">Sort By</label>
				<select id="results-sort" name="sort" class="form-control">
					<option value="title" {if $sort=='title'}selected="selected"{/if}>Title</option>
					<option value="newest" {if $sort=='newest'}selected="selected"{/if}>Newest First</option>
					<option value="oldest" {if $sort=='oldest'}selected="selected"{/if}>Oldest First</option>
					<option value="dateAdded" {if $sort=='dateAdded'}selected="selected"{/if}>Date Added</option>
					<option value="dateModified" {if $sort=='dateModified'}selected="selected"{/if}>Date Modified</option>
				</select>
					<span class="input-group-btn">
						<button class="btn btn-primary" onclick="el=document.getElementById('results-sort');Pika.Archive.sort = el.options[el.selectedIndex ].value;
						{if $displayType == 'map'}
										Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 1);
						{elseif $displayType == 'mapNoTimeline'}
										Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 1);
						{elseif $displayType == 'timeline'}
										Pika.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 1);
						{elseif $displayType == 'scroller'}
										Pika.Archive.reloadScrollerResults('{$exhibitPid|urlencode}', 1);
						{elseif $displayType == 'basic'}
										Pika.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}', 1);
						{/if}">GO</button>
					</span>
				</div>
			</div>
		</div>
		{if !empty($label)}<h2>{$label}</h2>{/if}{* For accessibility, don't display an empty heading *}
		<div class="row">
			<div class="col-sm-4">
				{if $recordCount}
					{$recordCount} total objects.
				{/if}
			</div>
		</div>

		{if $displayType != 'basic' && $displayType != 'mapNoTimeline' && $displayType != 'scroller' && ($recordEnd < $recordCount || $updateTimeLine)}
			{* Display selection of date ranges *}
			<div class="row">
				<div class="col-xs-12">
					<div class="btn-group btn-group-sm" role="group" aria-label="Select Dates" data-toggle="buttons">
						<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array('unknown', $smarty.request.dateFilter)} active{/if}">
							{if $displayType == 'map'}
								<input name="dateFilter" onchange="Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" value="all"><strong>All</strong><br>({$recordCount})
							{elseif $displayType == 'timeline'}
								<input name="dateFilter" onchange="Pika.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" value="all"><strong>All</strong><br>({$recordCount})
							{/if}
						</label>
						{foreach from=$dateFacetInfo item=facet}
							<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array($facet.value, $smarty.request.dateFilter)} active{/if}">
								{if $displayType == 'map'}
									<input name="dateFilter" onchange="Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" autocomplete="off" value="{$facet.value}"><strong>{$facet.label}</strong><br>({$facet.count})
								{elseif $displayType == 'timeline'}
									<input name="dateFilter" onchange="Pika.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" autocomplete="off" value="{$facet.value}"{if !empty($smarty.request.dateFilter) && in_array($facet.value, $smarty.request.dateFilter)} checked="checked"{/if}><strong>{$facet.label}</strong><br>({$facet.count})
								{/if}
							</label>
						{/foreach}
						{if $numObjectsWithUnknownDate > 0}
							<div class="btn-group btn-group-sm" role="group" aria-label="Unknown Date" data-toggle="buttons">
								<label class="btn btn-default btn-sm{if !empty($smarty.request.dateFilter) && in_array('unknown', $smarty.request.dateFilter)} active{/if}">
									{if $displayType == 'map'}
										<input name="dateFilter" onchange="Pika.Archive.reloadMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', 0)" type="radio" autocomplete="off" value="unknown"><strong>Unknown</strong><br>({$numObjectsWithUnknownDate})
									{elseif $displayType == 'timeline'}
										<input name="dateFilter" onchange="Pika.Archive.reloadTimelineResults('{$exhibitPid|urlencode}', 0)" type="radio" autocomplete="off" value="unknown"><strong>Unknown</strong><br>({$numObjectsWithUnknownDate})
									{/if}
								</label>
							</div>
						{/if}
					</div>
				</div>
			</div>
		{/if}

		{include file="Archive/archiveCollections-displayMode-toggle.tpl"}

		<div class="clearer"></div>
		<div id="results">


	{/if}

	{if $solrError}
		<div class="alert alert-danger">{$solrError}</div>
		<a href="{$solrLink}">Link to solr query</a>
	{/if}

	{if $recordSet}
		{include file="Archive/list-list.tpl"}
	{else}
		<div id="related-exhibit-images" class="{if $showThumbnailsSorted}row{else}results-covers home-page-browse-thumbnails{/if}">
		{foreach from=$relatedObjects item=image}
			{if $showThumbnailsSorted}<div class="col-xs-6 col-sm-4 col-md-3">{/if}
				<figure class="{if $showThumbnailsSorted}browse-thumbnail-sorted{else}browse-thumbnail{/if}">
					<a href="{$image.link}" {if $image.title}data-title="{$image.title}"{/if} onclick="return Pika.Archive.showObjectInPopup('{$image.pid|urlencode}'{if $image.recordIndex},{$image.recordIndex}{if $page},{$page}{/if}{/if})">
						<img src="{$image.image}" {if $image.title}alt="{$image.title}"{/if}>
						<figcaption class="explore-more-category-title">
							<strong>{$image.title|truncate:50} ({$image.dateCreated})</strong>
						</figcaption>
					</a>
				</figure>
			{if $showThumbnailsSorted}</div>{/if}
		{/foreach}
	</div>
	{/if}

	<div id="nextInsertPoint" class="text-center">
		{* {$recordCount-$recordEnd} more records to load *}
		{if $recordEnd < $recordCount}

			{if $displayType == 'map' || $displayType == 'mapNoTimeline'}
				<button type="button" id="more-browse-results" onclick="return Pika.Archive.getMoreMapResults('{$exhibitPid|urlencode}', '{$placePid|urlencode}', '{if $displayType == 'map'}true{else}false{/if}')" aria-label="Load more objects">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</button>
			{elseif $displayType == 'timeline'}
				<button type="button" id="more-browse-results" onclick="return Pika.Archive.getMoreTimelineResults('{$exhibitPid|urlencode}')" aria-label="Load more objects">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</button>
			{elseif $displayType == 'scroller'}
				<button type="button" id="more-browse-results" onclick="return Pika.Archive.getMoreScrollerResults('{$exhibitPid|urlencode}')" aria-label="Load more objects">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</button>
			{else}
				<button type="button" role="button" onclick="return Pika.Archive.getMoreExhibitResults('{$exhibitPid|urlencode}')" id="more-browse-results">
					<span class="glyphicon glyphicon-chevron-down" aria-hidden="true"></span>
				</button>
			{/if}
		{/if}
	</div>
{/strip}