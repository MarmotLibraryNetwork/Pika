{strip}
	<div id="dplaSearchResults">
		{if $showDplaDescription}
			<h2 class="h3">More from Digital Public Library of America</h2>
			<p>The Digital Public Library of America brings together the riches of America’s libraries, archives, and museums, and makes them freely available to the world. It strives to contain the full breadth of human expression, from the written word, to works of art and culture, to records of America’s heritage, to the efforts and data of science. DPLA aims to expand this crucial realm of openly available materials, and make those riches more easily discovered and more widely usable and used.</p>
		{/if}
		{foreach from=$searchResults item=result name="recordLoop"}
			<div class="dplaResult result">
				<div class="row {*result-title-row*}">
					<div class="col-tn-12">
						<h3 class="h4">
							<span class="result-index">{$smarty.foreach.recordLoop.iteration}.</span>&nbsp;
							<a class="result-title" href="{$result.link}">{$result.title}</a>
						</h3>
					</div>
				</div>
				<div class="row">
					{if $showCovers}
						<div class="col-tn-4 col-md-2">
							{if $result.object}
								<img src="{$result.object}" class="listResultImage img-thumbnail img-responsive" alt="Thumbnail for '{$result.title}'">
							{/if}
						</div>
					{/if}
					<div class="{if $showCovers}col-tn-8 col-md-10{else}col-tn-12{/if}">
						{if $result.format}
							<div class="row">
								<div class="result-label col-tn-2">{translate text='Format'}:</div>
								<div class="col-tn-10 result-value">{$result.format|escape}</div>
							</div>
						{/if}

						<div class="row">
						<p>{$result.description|truncate_html:450:"..."}</p>
						</div>
					</div>
				</div>
			</div>
		{/foreach}
	</div>
{/strip}