{strip}
	{if $browseMode == 'grid'}
		<div class="browse-list">
			<a onclick="return Pika.GroupedWork.showGroupedWorkInfo('{$summId}', '{$browseCategoryId}')" href="{$summUrl}">
					<img class="img-responsive" src="{$bookCoverUrl}" alt=""{* Empty alt text since is just duplicates the link text*} {*alt="{$summTitle}{if $summAuthor} by {$summAuthor}{/if}"*} title="{$summTitle}{if $summAuthor} by {$summAuthor}{/if}">
				<div><strong>{$summTitle}</strong>{if $summAuthor}<br> by {$summAuthor}{/if}</div>
			</a>
		</div>

	{else}{*Default Browse Mode (covers) *}
		<div class="column-fix"> {* Needed div to hack firefox into not doing column breaks with a thumbnail. Only used for Sacramento at this time. pascal 10-19-2018 *}
		<div class="browse-thumbnail">
			<a onclick="return Pika.GroupedWork.showGroupedWorkInfo('{$summId}','{$browseCategoryId}')" href="{$summUrl}">
				<div>
					<img src="{$bookCoverUrlMedium}" alt="{$summTitle}{if $summAuthor} by {$summAuthor}{/if}" title="{$summTitle}{if $summAuthor} by {$summAuthor}{/if}">
				</div>
			</a>
			{if $showRatings && $browseCategoryRatingsMode != 'none'}
				<div class="browse-rating{if $browseCategoryRatingsMode == 'stars'} rater{/if}"
				{if $browseCategoryRatingsMode == 'popup'} onclick="return Pika.GroupedWork.showReviewForm(this, '{$summId}');" style="cursor: pointer"
					{literal}onkeydown="if(event.key === 'Enter') { return Pika.GroupedWork.showReviewForm(this, '{/literal}{$summId}{literal}'); }"{/literal} 
				{/if}
				{if $browseCategoryRatingsMode == 'stars'} {* keep space between attributes *}
					{* AJAX rater data fields *}
					{*{if $ratingData.user}data-user_rating="{$ratingData.user}" {/if}*}{* Don't show user ratings in browse results because the results get cached so shouldn't be particular to a single user.*}
					data-average_rating="{$ratingData.average}" data-id="{$summId}" {* keep space between attributes *}
					data-show_review="{$showComments}"
				{/if}
				tabindex="0">{* finishes this div tag above *}

				<span class="ui-rater-starsOff" style="width:90px">
					{* Don't show a user's ratings in browse results because the results get cached so shouldn't be particular to a single user.*}
					<span class="ui-rater-starsOn" style="width:{math equation="90*rating/5" rating=$ratingData.average}px"></span>
				</span>
				</div>
			{/if}
		</div>
		</div>
	{/if}
{/strip}

