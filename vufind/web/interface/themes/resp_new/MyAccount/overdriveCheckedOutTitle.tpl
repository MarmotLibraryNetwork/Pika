{strip}
<div id="overdrive_{if $record.overdriveMagazine}{$record.issueId|escape}{else}{$record.overDriveId|escape}{/if}" class="result row">

	{* Cover Column *}
	{if $showCovers}
		<div class="col-xs-3 col-sm-4 col-md-3 checkedOut-covers-column">
			<div class="row">
				<div class="selectTitle hidden-xs col-sm-1">
					&nbsp;{* Can't renew overdrive titles*}
				</div>
				<div class="{*coverColumn *}text-center col-xs-12 col-sm-10">
					{if $disableCoverArt != 1}{*TODO: should become part of $showCovers *}
						{if $record.coverUrl}
							{if $record.recordId && $record.linkUrl}
								<a href="{$record.linkUrl}" id="descriptionTrigger{$record.recordId|escape:"url"}">
									<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}">
								</a>
							{else} {* Cover Image but no Record-View link *}
								<img src="{$record.coverUrl}" class="listResultImage img-thumbnail img-responsive" alt="{translate text='Cover Image'}">
							{/if}
						{/if}
					{/if}
				</div>
			</div>
		</div>
	{else}
		<div class="col-xs-1">
			&nbsp;{* Can't renew overdrive titles*}
		</div>
	{/if}

	{* Title Details Column *}
	<div class="{if $showCovers}col-xs-9 col-sm-8 col-md-9{else}col-xs-11{/if}">
		{* Title *}
		<div class="row">
			<div class="col-xs-12">
				<span class="result-index">{$resultIndex})</span>&nbsp;
				{if $record.linkUrl}
					<a href="{$record.linkUrl}" class="result-title notranslate">
						{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}
					</a>
				{else}
					<span class="result-title notranslate">
							{if !$record.title|removeTrailingPunctuation}{translate text='Title not available'}{else}{$record.title|removeTrailingPunctuation|truncate:180:"..."|highlight}{/if}
						</span>
				{/if}
			</div>
		</div>
		<div class="row">
			<div class="resultDetails col-xs-12 col-md-9">
				{if strlen($record.author) > 0}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">{translate text='Author'}</div>
						<div class="result-value col-tn-8 col-lg-9">{$record.author}</div>
					</div>
				{/if}

				<div class="row">
					<div class="result-label col-tn-4 col-lg-3">{translate text='Source'}</div>
					<div class="result-value col-tn-8 col-lg-9">{$record.checkoutSource}</div>
				</div>

				{if $record.checkoutdate}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">{translate text='Checked Out'}</div>
						<div class="result-value col-tn-8 col-lg-9">{$record.checkoutdate|date_format}</div>
					</div>
				{/if}

				{if $record.edition}
					<div class="row">
						{*This serves as the issue name for Magazines *}
						<div class="result-label col-tn-4 col-lg-3">{translate text='Edition'}</div>
						<div class="result-value col-tn-8 col-lg-9">{$record.edition}</div>
					</div>
				{/if}

				{if $record.isFormatSelected}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">{translate text='Format'}</div>
						<div class="result-value col-tn-8 col-lg-9">{$record.selectedFormat.name}</div>
					</div>
				{/if}

				{if $showRatings && $record.groupedWorkId && $record.ratingData}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">Rating&nbsp;</div>
						<div class="result-value col-tn-8 col-lg-9">
							{include file="GroupedWork/title-rating.tpl" ratingClass="" id=$record.groupedWorkId ratingData=$record.ratingData showNotInterested=false}
						</div>
					</div>
				{/if}

				{if $hasLinkedUsers}
					<div class="row">
						<div class="result-label col-tn-4 col-lg-3">{translate text='Checked Out To'}</div>
						<div class="result-value col-tn-8 col-lg-9">
							{$record.user}
						</div>
					</div>
				{/if}

				<div class="row">
					<div class="result-label col-tn-4 col-lg-3">{translate text='Expires'}</div>
					<div class="result-value col-tn-8 col-lg-9">{$record.dueDate|date_format}</div>
				</div>

		</div>

				{* Actions for Title *}
			<div class="col-xs-9 col-sm-8 col-md-4 col-lg-3">
				<div class="btn-group btn-group-vertical btn-block">
					{if $record.overdriveMagazine}
						<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink('{$record.userId}', '{$record.issueId}', 'magazine-overdrive')" class="btn btn-sm btn-primary">Get Magazine</a>
					{else}
						<a href="#" onclick="return Pika.OverDrive.followOverDriveDownloadLink('{$record.userId}', '{$record.overDriveId}')" class="btn btn-sm btn-primary">Get {if $record.mediaType}{$record.mediaType}{else}eContent{/if}</a>
          {/if}

					{if $record.earlyReturn}
						<a href="#" onclick="return Pika.OverDrive.returnOverDriveTitle('{$record.userId}', '{if $record.overdriveMagazine}{$record.issueId}{else}{$record.overDriveId}{/if}');" class="btn btn-sm btn-warning">Return&nbsp;Now</a>
					{/if}
				</div>

			</div>
		</div>

		{if !empty($record.supplementalTitle)}
				{* Note: the supplemental title template has modifications of html built here *}
				<br>
				{foreach from=$record.supplementalTitle key="keyIgnored" item="supplementalTitle"}
					{include file="MyAccount/overdriveSupplementTitle.tpl" }
        {/foreach}
		{/if}
	</div>
</div>
{/strip}