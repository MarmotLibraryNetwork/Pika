{strip}
	{assign var=listId value=$summId|replace:'list':''}
	{if $browseMode == 'grid'}
	<div class="browse-list">
		<a {*onclick="return alert('{$summId}'" *} href="{$summUrl}">
			{*<div>*}

			<img class="img-responsive" src="/bookcover.php?id={$listId}&size=medium&type=userList" alt=""{* Empty alt text since is just duplicates the link text*} {*alt="{$summTitle} by {$summAuthor}"*} title="{$summTitle} by {$summAuthor}">
			{*</div>*}
			<div><strong>{$summTitle} </strong><br> by {$summAuthor}</div>
		</a>
	</div>

	{else}{*Default Browse Mode (covers) *}

	<div class="{*browse-title thumbnail *}browse-thumbnail">
		{* thumbnail styling added to browse-thumbnail as mix in, browse-title not in use. plb 4-27-2015 *}
		{*<a onclick="return Pika.GroupedWork.showGroupedWorkInfo('{$summId}', '{$browseCategoryId}')" href="{$summUrl}">*}
		<a {*onclick="return alert('{$summId}'" *} href="{$summUrl}">
			{*  TODO: add pop-up for list *}
			<div>
				<img src="/bookcover.php?id={$listId}&size=medium&type=userList{*$bookCoverUrlMedium*}" alt="{$summTitle} by {$summAuthor}" title="{$summTitle} by {$summAuthor}">
			</div>
		</a>
		{*{if $showComments}*}
			{*<div class="browse-rating" onclick="return Pika.GroupedWork.showReviewForm(this, '{$summId}');">*}
				{*<span class="ui-rater-starsOff" style="width:90px">*}
					{*{if $ratingData.user}*}
						{*<span class="ui-rater-starsOn userRated" style="width:{math equation="90*rating/5" rating=$ratingData.user}px"></span>*}
					{*{else}*}
						{*<span class="ui-rater-starsOn" style="width:{math equation="90*rating/5" rating=$ratingData.average}px"></span>*}
					{*{/if}*}
				{*</span>*}
			{*</div>*}
		{*{/if}*}
	</div>
	{/if}
{/strip}