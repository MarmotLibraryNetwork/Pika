{strip}

	<div id="list-magazineIssues"{if $display == 'false'} style="display:none"{/if} class="titleScroller tab-pane{if $active} active{/if}{if $widget->coverSize == 'medium'} mediumScroller{/if}{if $widget->showRatings} scrollerWithRatings{/if}">
		<div id="magazineIssues" class="titleScrollerWrapper">
			{if $showListWidgetTitle || $showViewMoreLink || $Links}
				<div id="list-{$wrapperId}Header" class="titleScrollerHeader">
					{if $showListWidgetTitle}
						<span class="listTitle resultInformationLabel">{if $scrollerTitle}{$scrollerTitle|escape:"html"}{/if}</span>
					{/if}

					{if $Links}
						{foreach from=$Links item=link}
							<div class="linkTab">
								<a href='{$link->link}'><span class="seriesLink">{$link->name}</span></a>
							</div>
						{/foreach}
					{elseif $showViewMoreLink && strlen($fullListLink) > 0}
						<div class="linkTab" style="float:right">
							<a href='{$fullListLink}'><span class="seriesLink">View More</span></a>
						</div>
					{/if}

				</div>
			{/if}
			<div id="titleScrollerIssues" class="titleScrollerBody">
				<div class="leftScrollerButton enabled btn issuesLeft"> <i class="glyphicon glyphicon-chevron-left"></i></div>
				<div class="rightScrollerButton btn issuesRight"><i class="glyphicon glyphicon-chevron-right"></i></div>
				<div class="scrollerBodyContainer">
					<div class="scrollerBody" style="display:none"></div>
					<div class="scrollerLoadingContainer">
						<img id="scrollerLoadingImageIssues" class="scrollerLoading" src="{img filename="loading_large.gif"}" alt="Loading...">
					</div>
				</div>
				<div class="clearer"></div>
				{if !isset($widget) || $widget->showTitle}
					<div id="titleScrollerSelectedTitleIssues" class="titleScrollerSelectedTitle notranslate"></div>
				{/if}
				{if !isset($widget) || $widget->showAuthor}
					<div id="titleScrollerSelectedAuthorIssues" class="titleScrollerSelectedAuthor notranslate"></div>
				{/if}
			</div>
		</div>
	</div>
	<script type="text/javascript">
		{*//	 touch swiping controls *}
		$(document).ready(function(){ldelim}
			var scrollFactor = 10; {*// swipe size per item to scroll.*}
			var issuesTitleScroller = new TitleScroller("titleScrollerIssues", "Issues", "titleScrollerIssues");
			var ajaxUrl = "/OverDrive/AJAX?method=getIssuesList&parentId=" + '{$id}';
			issuesTitleScroller.loadIssuesFromAjax(ajaxUrl);
			$('#titleScrollerIssues .scrollerBodyContainer')
							.touchwipe({ldelim}
								wipeLeft : function(dx){ldelim}
									var scrollInterval = Math.round(dx / scrollFactor); {*// vary scroll interval based on wipe length *}
									issuesTitleScroller.swipeToLeft(scrollInterval);
									{rdelim},
								wipeRight: function(dx) {ldelim}
									var scrollInterval = Math.round(dx / scrollFactor); {*// vary scroll interval based on wipe length *}
									issuesTitleScroller.swipeToRight(scrollInterval);
									{rdelim}
								{rdelim});
			$(".issuesLeft").click(function(){ldelim}

				issuesTitleScroller.scrollToLeft();
				{rdelim});
			$(".issuesRight").click(function(){ldelim}
				issuesTitleScroller.scrollToRight();
				{rdelim});
			{rdelim});

	</script>

{/strip}
