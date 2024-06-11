{strip}
<div id="list-{$wrapperId}"{if $display == 'false'} style="display:none"{/if} class="titleScroller tab-pane{if $active} active{/if}{if $widget->coverSize == 'medium'} mediumScroller{/if}{if $widget->showRatings} scrollerWithRatings{/if}">
	<div id="{$wrapperId}" class="titleScrollerWrapper">
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
		<div id="titleScroller{$scrollerName}" class="titleScrollerBody">
			<button class="leftScrollerButton enabled btn btn-default" onclick="{$scrollerVariable}.scrollToLeft();" tabindex="0" aria-label="Scroll left"><i class="glyphicon glyphicon-chevron-left"></i></button>

			<div class="scrollerBodyContainer">
				<div class="scrollerBody" style="display:none"></div>
				<div class="scrollerLoadingContainer">
					<img id="scrollerLoadingImage{$scrollerName}" class="scrollerLoading" src="{img filename="loading_large.gif"}" alt="Loading...">
				</div>
			</div>
			<div class="clearer"></div>
			{if !isset($widget) || $widget->showTitle}
				<div id="titleScrollerSelectedTitle{$scrollerName}" class="titleScrollerSelectedTitle notranslate"></div>
			{else}
				<div id="titleScrollerSelectedTitle{$scrollerName}" aria-live="polite" class="visuallyhidden titleScrollerSelectedTitle notranslate"></div>
			{/if}
			{if !isset($widget) || $widget->showAuthor}
				<div id="titleScrollerSelectedAuthor{$scrollerName}" class="titleScrollerSelectedAuthor notranslate"></div>
			{/if}
			<button class="rightScrollerButton btn btn-default" onclick="{$scrollerVariable}.scrollToRight();" tabindex="0" aria-label="Scroll right"><i class="glyphicon glyphicon-chevron-right"></i></button>
		</div>
			{if isset($widget)}
		{if $widget->autoRotate}
		<div class="sliderControls">
{*			<button class="btn btn-primary slowDown glyphicon glyphicon-fast-backward" aria-label="Slow Down"><span class="visuallyhidden">Slow Down</span></button>*}
			<button class="btn btn-primary pause glyphicon glyphicon-pause" aria-label="Pause"><span class="visuallyhidden">Pause</span></button>
{*			<button class="btn btn-primary speedUp glyphicon glyphicon-fast-forward" aria-label="Speed Up"><span class="visuallyhidden">Speed Up</span></button>*}
		</div>
		{/if}
      {/if}
	</div>
</div>
<script>
{*//	 touch swiping controls *}
	$(function(){ldelim}
		var scrollFactor = 10; {*// swipe size per item to scroll.*}
		$('#titleScroller{$scrollerName} .scrollerBodyContainer')
			.touchwipe({ldelim}
				wipeLeft : function(dx){ldelim}
					var scrollInterval = Math.round(dx / scrollFactor); {*// vary scroll interval based on wipe length *}
					{$scrollerVariable}.swipeToLeft(scrollInterval);
				{rdelim},
				wipeRight: function(dx) {ldelim}
					var scrollInterval = Math.round(dx / scrollFactor); {*// vary scroll interval based on wipe length *}
					{$scrollerVariable}.swipeToRight(scrollInterval);
				{rdelim}
		{rdelim});

		$('#titleScroller{$scrollerName} .scrollerBodyContainer').parent('.titleScrollerBody').parent('.titleScrollerWrapper').children('.sliderControls').on('click', 'button.pause, button.play', function(){ldelim}
			TitleScroller.prototype.playPauseControl(this, {$scrollerVariable});
		{rdelim});
						/*.on('click', 'button.speedUp', function() {ldelim}
								TitleScroller.prototype.fasterControl({$scrollerVariable},{$scrollerVariable}.interval);
            {rdelim})
						.on('click', 'button.slowDown', function() {ldelim}
								TitleScroller.prototype.slowerControl({$scrollerVariable}, {$scrollerVariable}.interval);
						{rdelim});*/
	{rdelim});

</script>
{/strip}