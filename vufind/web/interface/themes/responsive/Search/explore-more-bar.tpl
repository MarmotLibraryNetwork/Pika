{strip}
	{* TODO: Consider renaming classes to assume they are under the exploreMoreBar class *}
<aside class="exploreMoreBar row" aria-labelledby="exploreMoreBarLabel">
	{*<div class="label-left">*}
	<div class="label-top">
		<img src="{img filename='/interface/themes/responsive/images/ExploreMore.png'}" alt=""{* "Alternative text of images should not be repeated as text" *}>
		<div id="exploreMoreBarLabel" class="exploreMoreBarLabel">{translate text='Explore More'}</div>
	</div>

	<div class="exploreMoreContainer">
		<div class="jcarousel-wrapper" id="exploreMoreBarScroll">
			{* Scrolling Buttons *}
			<button class="jcarousel-control-prev"{* data-target="-=1"*} aria-label="Previous Category"><i class="glyphicon glyphicon-chevron-left"></i></button>

				{assign var='i' value=0}
			<div class="exploreMoreItemsContainer jcarousel"{* data-wrap="circular" data-jcarousel="true"*}> {* noIntialize is a filter for Pika.initCarousels() *}
				<ul>

					{foreach from=$exploreMoreOptions item=exploreMoreCategory}
						{if $exploreMoreCategory.placeholder}
							<li id="carousel{$i}">
								<a href='{$exploreMoreCategory.link}'>
									<img src="{$exploreMoreCategory.image}" alt="{$exploreMoreCategory.label|escape}">
								</a>
							</li>
						{else}
							<li id="exploreMore{$i}" class="explore-more-option">
								<figure class="thumbnail" title="{$exploreMoreCategory.label|escape}">
									<div class="explore-more-image">
										<a href='{$exploreMoreCategory.link}' title="{$exploreMoreCategory.label|escape}">
											<img src="{$exploreMoreCategory.image}" alt="{$exploreMoreCategory.label|escape}">
										</a>
									</div>
									<figcaption class="explore-more-category-title">
										<strong>{$exploreMoreCategory.label|truncate:30}</strong>
									</figcaption>
								</figure>
							</li>
						{/if}
							{assign var='i' value=$i++}
					{/foreach}
				</ul>
			</div>
			<button class="jcarousel-control-next"{* data-target="+=1"*} aria-label="Next Category"><i class="glyphicon glyphicon-chevron-right"></i></button>
		</div>
	</div>

</aside>
{/strip}

