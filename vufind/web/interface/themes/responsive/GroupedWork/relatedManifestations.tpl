{strip}
	<div class="related-manifestations">
		<div class="row related-manifestations-header">
			<div class="col-sm-12 result-label related-manifestations-label">
				{translate text="Choose a Format"}
			</div>
		</div>
		{assign var=hasHiddenFormats value=false}
		{foreach from=$relatedManifestations item=relatedManifestation}
			{if $relatedManifestation.hideByDefault}
				{assign var=hasHiddenFormats value=true}
			{/if}
			<div class="row related-manifestation {if $relatedManifestation.hideByDefault}hiddenManifestation_{$summId}{/if}" {if $relatedManifestation.hideByDefault}style="display: none"{/if}>
				<div class="col-md-12">
				  <div class="row">
						<div class="col-5 col-sm-4{if !$viewingCombinedResults} col-lg-4{/if} manifestation-format">
{*							<button class="btn-link" onclick="return Pika.showElementInPopup('Edition', '#relatedRecordPopup_{if $inPopUp}popup-{/if}{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}');">*}
							<button class="btn-link" onclick="return Pika.ResultsList.toggleRelatedManifestations('{if $inPopUp}popup-{/if}{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}');">
								{$relatedManifestation.format}<br>
								{if $relatedManifestation.numRelatedRecords == 1}
									<span class="manifestation-toggle-text badge text-bg-secondary" id='manifestation-toggle-text-{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}'>Show&nbsp;Edition</span>
								{else}
									<span class="manifestation-toggle-text badge text-bg-info" id='manifestation-toggle-text-{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}'>Show&nbsp;Editions</span>
								{/if}
							</button>
						</div>
						<div class="col-7 col-sm-8{if !$viewingCombinedResults} col-lg-4 col-xl-5{/if}">
							{include file='GroupedWork/statusIndicator.tpl' statusInformation=$relatedManifestation viewingIndividualRecord=0}
						</div>
					  {*  Work here *}
						<div class="col-12{*col-9 offset-3*} col-sm-8 offset-sm-4{if !$viewingCombinedResults} col-lg-4 offset-lg-0 col-xl-3{/if} manifestation-actions">
							<div class="btn-toolbar">
								<div class="btn-group btn-group-vertical btn-block">
									{foreach from=$relatedManifestation.actions item=curAction}
										{if !empty($curAction.url)}
											{if $relatedManifestation.numRelatedRecords == 1}
												<a href="{$curAction.url}" {if $curAction.openTab}target="_blank" {/if}class="btn btn-sm btn-primary" {if $curAction.requireLogin}onclick="return Pika.Account.followLinkIfLoggedIn(this, '{$curAction.url}');"{/if} {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</a>
											{else}
												<button class="btn btn-sm btn-primary" onclick="return Pika.ResultsList.toggleRelatedManifestations('{if $inPopUp}popup-{/if}{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}');">{translate text="econtent_available_from"}</button>
											{/if}
										{else}
											{if $relatedManifestation.isEContent || $relatedManifestation.format == 'Physical Object'}
												{if $relatedManifestation.numRelatedRecords == 1}
													<button class="btn btn-sm btn-primary" onclick="{$curAction.onclick}" {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</button>
												{else}
													<button class="btn btn-sm btn-primary" onclick="return Pika.ResultsList.toggleRelatedManifestations('{if $inPopUp}popup-{/if}{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}');">{if $relatedManifestation.format == 'Physical Object'}Show Options{else}{translate text="econtent_available_from"}{/if}</button>
												{/if}
											{else}
												<button class="btn btn-sm btn-primary" onclick="{$curAction.onclick}" {if $curAction.alt}title="{$curAction.alt}"{/if}>{$curAction.title}</button>
											{/if}
										{/if}
									{/foreach}
								</div>
							</div>
						</div>
				  </div>
					<div class="row">
						<div class="col-11 offset-1">
							{if $relatedManifestation.numRelatedRecords == 1}
								{include file='GroupedWork/copySummary.tpl' summary=$relatedManifestation.itemSummary totalCopies=$relatedManifestation.copies itemSummaryId=$id recordViewUrl=$relatedManifestation.url}
							{else}
								{include file='GroupedWork/copySummary.tpl' summary=$relatedManifestation.itemSummary totalCopies=$relatedManifestation.copies itemSummaryId=$id}
							{/if}
						</div>
					</div>

					<div class="row">
						<div class="col-md-12{*{if $relatedManifestation.numRelatedRecords != 1}*} hidden{*{/if}*}" id="relatedRecordPopup_{if $inPopUp}popup-{/if}{$id|escapeCSS}_{$relatedManifestation.format|escapeCSS}">
							{include file="GroupedWork/relatedRecords.tpl" relatedRecords=$relatedManifestation.relatedRecords relatedManifestation=$relatedManifestation}
						</div>
					</div>
				</div>
			</div>
		{foreachelse}
			<div class="row related-manifestation">
				<div class="col-md-12">
					The library does not own any copies of this title.
				</div>
			</div>
		{/foreach}
		{if $hasHiddenFormats}
			<div class="row related-manifestation" id="formatToggle_{$summId}">
				<div class="col-md-12">
					<button class="btn-link" onclick="$('.hiddenManifestation_{$summId}').show();$('#formatToggle_{$summId}').hide();return false;">View all Formats</button>
				</div>
			</div>
		{/if}
	</div>
{/strip}
