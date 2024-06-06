{if $showRatings || $showComments}
{strip}
	<div class="full-rating">
		{if $showRatings}
				<span>Your Rating:</span>
				<div class="title-rating"
				     data-user_rating="{if $ratingData.user}$ratingData.user{else}0{/if}"
				     data-rating_title="{$ratingTitle}"
				     data-id="{$recordDriver->getPermanentId()}"
				     data-show_review="{if $showComments && !$user->noPromptForUserReviews}1{else}0{/if}"
				>
					{include file='MyAccount/star-rating.tpl' id=$recordDriver->getPermanentId() ratingData=$ratingData ratingTitle=$ratingTitle}
				</div>
			<script>
				initStarRatings();
			</script>

{*			<div class="average-rating row{if !$ratingData.user} rater{/if}" *}{*onclick="return Pika.GroupedWork.showReviewForm(this, '{$recordDriver->getPermanentId()}')"*}
{*							{if !$ratingData.user} *}{* When user is not logged in or has not rating the work *}
{*								*}{* AJAX rater data fields *}
{*								data-average_rating="{$ratingData.average}" data-id="{$recordDriver->getPermanentId()}" *}{* keep space between attributes *}
{*								data-show_review="{if $showComments  && (!$user || !$user->noPromptForUserReviews)}1{else}0{/if}"*}
{*								*}{*  Show Reviews is enabled and the user hasn't opted out or user hasn't logged in yet. *}
{*							{/if}*}
{*							>*}
{*				*}
{*				<div class="col-xs-12 col-sm-6">*}
{*			<span class="ui-rater-starsOff" style="width:90px">*}
{*					<span class="ui-rater-starsOn" style="width:{math equation="90*rating/5" rating=$ratingData.average}px"></span>*}
{*				</span>*}
{*				</div>*}
{*			</div>*}

			{if $ratingData.average > 0}{* Only show histogram when there is rating data *}
			<div class="{* rating-label *}col-xs-12 col-sm-5" style="display: block;clear: both;">Other Ratings:</div>
			<br>
			<div class="rating-graph">
				<div class="row">
					<div class="col-xs-4">5 star</div>
					<div class="col-xs-5"><div class="graph-bar" style="width:{$ratingData.barWidth5Star}%">&nbsp;</div></div>
					<div class="col-xs-2">({$ratingData.num5star})</div>
				</div>
				<div class="row">
					<div class="col-xs-4">4 star</div>
					<div class="col-xs-5"><div class="graph-bar" style="width:{$ratingData.barWidth4Star}%">&nbsp;</div></div>
					<div class="col-xs-2">({$ratingData.num4star})</div>
				</div>
				<div class="row">
					<div class="col-xs-4">3 star</div>
					<div class="col-xs-5"><div class="graph-bar" style="width:{$ratingData.barWidth3Star}%">&nbsp;</div></div>
					<div class="col-xs-2">({$ratingData.num3star})</div>
				</div>
				<div class="row">
					<div class="col-xs-4">2 star</div>
					<div class="col-xs-5"><div class="graph-bar" style="width:{$ratingData.barWidth2Star}%">&nbsp;</div></div>
					<div class="col-xs-2">({$ratingData.num2star})</div>
				</div>
				<div class="row">
					<div class="col-xs-4">1 star</div>
					<div class="col-xs-5"><div class="graph-bar" style="width:{$ratingData.barWidth1Star}%">&nbsp;</div></div>
					<div class="col-xs-2">({$ratingData.num1star})</div>
				</div>
			</div>
			{/if}

		{/if} {* end if show ratings *}
		{if $showComments && !$hideReviewButton}{* Add hideReviewButton=true to {include} tag to disable below *}
			<div class="row">
				<div class="col-xs-12 text-center">
					<span id="userreviewlink{$recordDriver->getPermanentId()}" class="userreviewlink btn btn-sm" title="Add a Review" onclick="return Pika.GroupedWork.showReviewForm(this, '{$recordDriver->getPermanentId()}')">
						Add a Review
					</span>
				</div>
			</div>
		{/if}
	</div>
{/strip}
{/if}