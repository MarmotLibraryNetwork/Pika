<h1 role="heading" aria-level="1" class="h2">Archive Home</h1>

	<div class="col-xs-12">
		{if count($relatedContentTypes) == 0 && count($relatedProjectsLibrary) == 0 && count($relatedProjectsOther) == 0}
			<div class="row">
				<div class="col-xs-12">
					No content is available in the archive yet, please check back later.
				</div>
			</div>
		{else}
			{if !empty($relatedProjectsLibrary)}
				<div class="row">
					<div class="col-xs-12">
						<h2 class="h3"><a href="{$libraryProjectsUrl}">Collections from {$archiveName}</a></h2>
						<div id="relatedProjectScroller" class="jcarousel-wrapper">
							<a href="#" class="jcarousel-control-prev"><i class="glyphicon glyphicon-chevron-left"></i></a>
							<a href="#" class="jcarousel-control-next"><i class="glyphicon glyphicon-chevron-right"></i></a>

							<div class="relatedTitlesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
								<ul>
									{foreach from=$relatedProjectsLibrary item=project}
										<li class="relatedTitle">
											<a href="{$project.link}">
												<figure class="thumbnail">
													<img src="{$project.image}" alt="{* alt text should not duplicate captions *}">
													<figcaption>{$project.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
												</figure>
											</a>
										</li>
									{/foreach}
								</ul>
							</div>
						</div>
					</div>
				</div>
			{/if}

			{if !empty($relatedProjectsOther)}
				<div class="row">
					<div class="col-tn-12">
						<h2 class="h3"><a href="{$otherProjectsUrl}">{if count($relatedProjectsLibrary) > 0}More collections{else}Collections{/if} from the archive</a></h2>
						<div id="relatedProjectOtherScroller" class="jcarousel-wrapper">
							<a href="#" class="jcarousel-control-prev"><i class="glyphicon glyphicon-chevron-left"></i></a>
							<a href="#" class="jcarousel-control-next"><i class="glyphicon glyphicon-chevron-right"></i></a>

							<div class="relatedTitlesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
								<ul>
									{foreach from=$relatedProjectsOther item=project}
										<li class="relatedTitle">
											<a href="{$project.link}">
												<figure class="thumbnail">
													<img src="{$project.image}" alt="{* alt text should not duplicate captions *}">
													<figcaption>{$project.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
												</figure>
											</a>
										</li>
									{/foreach}
								</ul>
							</div>
						</div>
					</div>
				</div>
			{/if}

			<div class="row">
				<div class="col-tn-12">
					<h2 class="h3">Types of materials in the archive</h2>
					<div id="relatedContentTypesContainer" class="jcarousel-wrapper">
						<a href="#" class="jcarousel-control-prev"><i class="glyphicon glyphicon-chevron-left"></i></a>
						<a href="#" class="jcarousel-control-next"><i class="glyphicon glyphicon-chevron-right"></i></a>

						<div class="relatedTitlesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
							<ul>
								{foreach from=$relatedContentTypes item=contentType}
									<li class="relatedTitle">
										<a href="{$contentType.link}">
											<figure class="thumbnail">
												<img src="{$contentType.image}" alt="{* alt text should not duplicate captions *}">
												<figcaption>{$contentType.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
											</figure>
										</a>
									</li>
								{/foreach}
							</ul>
						</div>
					</div>
				</div>
			</div>
		{/if}
	</div>
