{strip}
<div class="col-xs-12">
	{if $parentExhibitUrl}
		{* Search/Archive Navigation for Exhibits within an exhibit *}
		{include file="Archive/search-results-navigation.tpl"}
	{/if}

	{if $main_image}
		<div class="main-project-image">
			<img src="{$main_image}" class="img-responsive" usemap="#map" alt='Main image for "{$title}" collection'>
		</div>
	{/if}

	<h1 role="heading" class="h2">
		{$title}
		{*{$title|escape} // plb 3/8/2017 not escaping because some titles use &amp; *}
	</h1>

	<div class="lead">
		{if $thumbnail && !$main_image}
			{if $exhibitThumbnailURL}<a href="{$exhibitThumbnailURL}">{/if}
			<img src="{$thumbnail}" class="img-responsive thumbnail exhibit-thumbnail" alt='Thumbnail for "{$title}" collection'>
			{if $exhibitThumbnailURL}</a>{/if}
		{/if}
		{$description}
	</div>

	<div class="clear-both"></div>

	<div id="related-objects-for-exhibit">
		<div id="exhibit-results-loading" class="row">
			<div class="alert alert-info">
				Updating results, please wait.
			</div>
		</div>
	</div>

	{if $repositoryLink && $loggedIn && $userRoles && (in_array('archives', $userRoles) || in_array('opacAdmin', $userRoles) || in_array('libraryAdmin', $userRoles))}
		<div id="more-details-accordion" class="panel-group">
			<div class="panel {*active*}{*toggle on for open*}" id="staffViewPanel">
				<a href="#staffViewPanelBody" data-toggle="collapse">
					<div class="panel-heading">
						<div class="panel-title">
							Staff View
						</div>
					</div>
				</a>
				<div id="staffViewPanelBody" class="panel-collapse collapse {*in*}{*toggle on for open*}">
					<div class="panel-body">
						<a class="btn btn-small btn-default" href="{$repositoryLink}" target="_blank">
							View in Islandora
						</a>
						<a class="btn btn-small btn-default" href="{$repositoryLink}/datastream/MODS/view" target="_blank">
							View MODS Record
						</a>
						<a class="btn btn-small btn-default" href="{$repositoryLink}/datastream/MODS/edit" target="_blank">
							Edit MODS Record
						</a>
						<a class="btn btn-small btn-default" href="#" onclick="return Pika.Archive.clearCache('{$pid}');" target="_blank">
							Clear Cache
						</a>
					</div>
				</div>
			</div>
		</div>
	{/if}
</div>
{/strip}
<script>
	$().ready(function(){ldelim}
		Pika.Archive.handleTimelineClick('{$pid|urlencode}');
		Pika.Archive.loadExploreMore('{$pid|urlencode}');
	{rdelim});
</script>