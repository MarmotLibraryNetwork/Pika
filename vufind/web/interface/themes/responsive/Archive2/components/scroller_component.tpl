{strip}
<style>{literal}.exploreMoreBar .explore-more-option .explore-more-image{text-align:center}.exploreMoreBar .explore-more-option .explore-more-image img{margin-left:auto;margin-right:auto}{/literal}</style>
<div class="nopadding col-sm-12">
	<div class="exhibitPage exploreMoreBar row">
		{if $browseCollectionTitle}
		<div class="label-top">
			<div class="exploreMoreBarLabel">
				<div class="archiveComponentHeader">{$browseCollectionTitle}</div>
			</div>
		</div>
		{/if}
		<div class="exploreMoreContainer">
			<div class="jcarousel-wrapper" id="scrollCollection{$browseCollectionNid}">
				<button class="jcarousel-control-prev"><i class="glyphicon glyphicon-chevron-left"></i></button>
				<div class="exploreMoreItemsContainer jcarousel">
					<ul>
						{foreach from=$browseCollectionItems item=item name="loop"}
						<li id="collectionItem{$item.nid}" class="explore-more-option">
							<a href="{$item.url}">
								<figure class="thumbnail" title="{$item.title|escape}">
									<div class="explore-more-image">
										{if $item.thumbnail}
										<img src="{$item.thumbnail}" alt="{$item.title|escape}">
										{/if}
									</div>
									<figcaption class="explore-more-category-title">
										<strong>{$item.title}</strong>
									</figcaption>
								</figure>
							</a>
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
