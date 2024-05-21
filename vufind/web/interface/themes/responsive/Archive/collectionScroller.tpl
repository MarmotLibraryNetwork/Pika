{strip}
    {assign var='i' value=0}
	<div class="nopadding col-sm-12">
		<div class="exhibitPage exploreMoreBar row">{* exhibitPage class overides some exploreMoreBar css*}
			{*<div class="label-left">*}
			<div class="label-top">
				<div class="exploreMoreBarLabel"><div class="archiveComponentHeader">{$browseCollectionTitlesData.title}</div></div>
			</div>

			<div class="exploreMoreContainer">
				<div class="jcarousel-wrapper" id="scroll{$browseCollectionTitlesData.title}">
					{* Scrolling Buttons *}
					<a href="#" class="jcarousel-control-prev"{* data-target="-=1"*}><i class="glyphicon glyphicon-chevron-left"></i></a>
					<div class="exploreMoreItemsContainer jcarousel"{* data-wrap="circular" data-jcarousel="true"*}> {* noIntialize is a filter for Pika.initCarousels() *}
						<ul>
							{foreach from=$browseCollectionTitlesData.collectionTitles item=titleInfo}
								<li id="exploreMore{$i}" class="explore-more-option">
									<figure class="thumbnail" title="{$titleInfo.title|escape}">
										<div class="explore-more-image">
											<a href='{$titleInfo.link}'{if $titleInfo.isExhibit} onclick="Pika.Archive.setForExhibitInAExhibitNavigation('{$browseCollectionTitlesData.collectionPid}')" {/if} {*{if $titleInfo.onclick}onclick="{$titleInfo.onclick}"{/if}*}>
												<img src="{$titleInfo.image}" alt="{$titleInfo.title|escape}">
											</a>
										</div>
										<figcaption class="explore-more-category-title">
											<strong>{$titleInfo.title}</strong>
										</figcaption>
									</figure>
								</li>
									{assign var='i' value=$i++}
							{/foreach}
						</ul>
					</div>
					<a href="#" class="jcarousel-control-next"{* data-target="+=1"*}><i class="glyphicon glyphicon-chevron-right"></i></a>
				</div>
			</div>
		</div>

		{*
		Remove this since we decided not to browse by clicking on the department.  If re-enabling, need to figure out conflicts
		with this and the browseCollectionComponent
		<div id="related-objects-for-exhibit">
			<div id="exhibit-results-loading" class="row" style="display: none">
				<div class="alert alert-info">
					Updating results, please wait.
				</div>
			</div>
		</div>
		*}
	</div>
{/strip}