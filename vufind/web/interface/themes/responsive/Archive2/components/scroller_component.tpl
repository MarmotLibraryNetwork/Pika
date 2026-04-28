{strip}
<div class="nopadding col-sm-12">
	<div class="exhibitPage exploreMoreBar row">
		<div class="label-top">
			<div class="exploreMoreBarLabel">
				<div class="archiveComponentHeader">{$browseCollectionTitle}</div>
			</div>
		</div>
		<div class="exploreMoreContainer">
			<div class="jcarousel-wrapper" id="scrollCollection{$browseCollectionTitle|escape}">
				<button class="jcarousel-control-prev"><i class="glyphicon glyphicon-chevron-left"></i></button>
				<div class="exploreMoreItemsContainer jcarousel">
					<ul>
						{foreach from=$browseCollectionItems item=item name="loop"}
						<li id="collectionItem{$smarty.foreach.loop.index}" class="explore-more-option">
							<figure class="thumbnail" title="{$item.title|escape}">
								<div class="explore-more-image">
									<a href="{$item.url}">
										{if $item.thumbnail}
										<img src="{$item.thumbnail}" alt="{$item.title|escape}">
										{/if}
									</a>
								</div>
								<figcaption class="explore-more-category-title">
									<strong>{$item.title}</strong>
								</figcaption>
							</figure>
						</li>
						{/foreach}
					</ul>
				</div>
				<button class="jcarousel-control-next"><i class="glyphicon glyphicon-chevron-right"></i></button>
			</div>
		</div>
	</div>
</div>
{/strip}
