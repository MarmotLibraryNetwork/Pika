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
				{assign var='n' value=0}
				<div class="row">
					<div class="col-xs-12">
						<h2 class="h3"><a href="{$libraryProjectsUrl}">Collections from {$archiveName}</a></h2>
						<div id="relatedProjectsLibraryScroller" class="jcarousel-wrapper">
							<button class="jcarousel-control-prev" aria-label="Previous Collection"><i class="glyphicon glyphicon-chevron-left"></i></button>


							<div class="relatedTitlesLibraryContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
								<ul>
									{foreach from=$relatedProjectsLibrary item=project}
										<li id="relatedTitleLibrary{$n}" class="relatedTitle">
											<a href="{$project.link}" >
												<figure class="thumbnail">
													<img src="{$project.image}" alt="Collection: {$project.title|removeTrailingPunctuation|truncate:40}">
													<figcaption>{$project.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
												</figure>
											</a>
										</li>
											{assign var='n' value= $n+1}
									{/foreach}
								</ul>
							</div>
							<button class="jcarousel-control-next" aria-label="Next Collection"><i class="glyphicon glyphicon-chevron-right"></i></button>
						</div>
					</div>
				</div>
			{/if}

			{if !empty($relatedProjectsOther)}
				{assign var='i' value=0}
				<div class="row">
					<div class="col-tn-12">
						<h2 class="h3"><a href="{$otherProjectsUrl}">{if count($relatedProjectsLibrary) > 0}More collections{else}Collections{/if} from the archive</a></h2>
						<div id="relatedProjectOtherScroller" class="jcarousel-wrapper">
							<button class="jcarousel-control-prev" aria-label="Previous Collection"><i class="glyphicon glyphicon-chevron-left"></i></button>
							<div class="relatedTitlesOtherContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
								<ul>
									{foreach from=$relatedProjectsOther item=project}
										<li id="relatedTitleOther{$i}" class="relatedTitle">
											<a href="{$project.link}" title="Collection: {$project.title|removeTrailingPunctuation|truncate:40}">
												<figure class="thumbnail">
													<img src="{$project.image}" alt="Collection: {$project.title|removeTrailingPunctuation|truncate:40}">
													<figcaption>{$project.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
												</figure>
											</a>
										</li>
											{assign var='i' value=$i+1}
									{/foreach}
								</ul>
							</div>
							<button class="jcarousel-control-next" aria-label="Next Collection"><i class="glyphicon glyphicon-chevron-right"></i></button>
						</div>
					</div>
				</div>
			{/if}
			{assign var='x' value=0}
			<div class="row">
				<div class="col-tn-12">
					<h2 class="h3">Types of materials in the archive</h2>
					<div id="relatedContentTypesContainer" class="jcarousel-wrapper">
						<button class="jcarousel-control-prev" aria-label="Previous Category"><i class="glyphicon glyphicon-chevron-left"></i></button>

						<div class="relatedTitlesTypesContainer jcarousel"> {* relatedTitlesContainer used in initCarousels *}
							<ul>
								{foreach from=$relatedContentTypes item=contentType}
									<li id="relatedTitleTypes{$x}" class="relatedTitle">
										<a href="{$contentType.link}">
											<figure class="thumbnail">
												<img src="{$contentType.image}" alt="Collection: {$project.title|removeTrailingPunctuation|truncate:40}">
												<figcaption>{$contentType.title|removeTrailingPunctuation|truncate:80:"..."}</figcaption>
											</figure>
										</a>
									</li>
										{assign var= 'x' value=$x+1}
								{/foreach}
							</ul>
						</div>
						<button class="jcarousel-control-next" aria-label="Next Category"><i class="glyphicon glyphicon-chevron-right"></i></button>
					</div>
				</div>
			</div>
		{/if}
	</div>
