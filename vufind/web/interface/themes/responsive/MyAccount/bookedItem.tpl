{strip}
<div class="result row">
	<div class="col-xs-12 col-sm-3">
		<div class="row">
			<div class="selectTitle col-xs-2">
				{if $myBooking->cancelValue}
					<input type="checkbox" name="cancelId[{$myBooking->userId}][{$myBooking->cancelName}]" value="{$myBooking->cancelValue}" id="selected{$myBooking->cancelValue}" class="titleSelect">&nbsp;
				{/if}
			</div>
			<div class="col-xs-9 text-center">
				{if $myBooking->id}
				<a href="{$myBooking->linkUrl}">
					{/if}
					<img src="{$myBooking->coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}">
					{if $myBooking->id}
				</a>
				{/if}
			</div>
		</div>
	</div>

	<div class="col-xs-12 col-sm-9">
		<div class="row">
			<div class="col-xs-12">
				<span class="result-index">{$resultIndex})</span>&nbsp;
				{if $myBooking->id}
					<a href="{$myBooking->linkUrl}" class="result-title notranslate">
				{/if}
				{if !$myBooking->title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$myBooking->title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}
				{if $myBooking->id}
					</a>
				{/if}
			</div>
		</div>

		<div class="row">
			<div class="resultDetails col-xs-12 col-md-9">

				{if $myBooking->author}
					<div class="row">
						<div class="result-label col-xs-3">{translate text='Author'}</div>
						<div class="col-xs-9 result-value">
							{if is_array($myBooking->author)}
								{foreach from=$myBooking->author item=author}
									<a href='/Author/Home?author="{$author|escape:"url"}"'>{$author|highlight}</a>
								{/foreach}
							{else}
								<a href='/Author/Home?author="{$myBooking->author|escape:"url"}"'>{$myBooking->author|highlight}</a>
							{/if}
						</div>
					</div>
				{/if}

				{if $myBooking->format}
					<div class="row">
						<div class="result-label col-xs-3">{translate text='Format'}</div>
						<div class="col-xs-9 result-value">
							{implode subject=$myBooking->format glue=", "}
						</div>
					</div>
				{/if}

        {if $showRatings && $myBooking->groupedWorkId && $myBooking->ratingData}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">{translate text='Rating'}</div>
						<div class="result-value col-tn-8 col-lg-9">
                {include file="GroupedWork/title-rating.tpl" ratingClass="" id=$myBooking->groupedWorkId ratingData=$myBooking->ratingData showNotInterested=false}
						</div>
					</div>
        {/if}

				{if $myBooking->user}
				<div class="row">
					<div class="result-label col-xs-3">{translate text='Scheduled For'}</div>
					<div class="col-xs-9 result-value">
						{$myBooking->userDisplayName}
					</div>
				</div>
				{/if}

				{if $myBooking->startDateTime == $myBooking->endDateTime}
					{* Items Booked for a day will have the same start & end. (time is usually 4) *}
					<div class="row">
						<div class="result-label col-xs-3">{translate text='Scheduled Date'}</div>
						<div class="col-xs-9 result-value">
							{$myBooking->startDateTime|date_format:"%b %d, %Y"} (All Day)
						</div>
					</div>
				{else}

					{* Otherwise display full datetime for start & end *}
					{if $myBooking->startDateTime}
						<div class="row">
							<div class="result-label col-xs-3">{translate text='Starting at'}</div>
							<div class="col-xs-9 result-value">
								{*{$myBooking->startDateTime|date_format:"%b %d, %Y at %l:%M %p"}*}
								{$myBooking->startDateTime|date_format:"%b %d, %Y"}
							</div>
						</div>
					{/if}

					{if $myBooking->endDateTime}
						<div class="row">
							<div class="result-label col-xs-3">{translate text='Ending at'}</div>
							<div class="col-xs-9 result-value">
								{*{$myBooking->endDateTime|date_format:"%b %d, %Y at %l:%M %p"}*}
								{$myBooking->endDateTime|date_format:"%b %d, %Y"}
							</div>
						</div>
					{/if}
				{/if}
				{if $myBooking->status}
					<div class="row">
						<div class="result-label col-xs-3">{translate text='Status'}</div>
						<div class="col-xs-9 result-value">{$myBooking->status}</div>
					</div>
				{/if}

			</div>

			<div class="col-xs-12 col-md-3">
				<div class="btn-group btn-group-vertical btn-block">
					{if $myBooking->cancelValue}
						<button onclick="return VuFind.Account.cancelBooking('{$myBooking->userId}', '{$myBooking->cancelValue}')" class="btn btn-sm btn-warning">Cancel Item</button>
					{/if}
				</div>
			</div>

		</div>
	</div>


</div>
{/strip}