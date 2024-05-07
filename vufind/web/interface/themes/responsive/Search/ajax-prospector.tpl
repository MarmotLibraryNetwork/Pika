{strip}
	<div class="row" id="prospectorSection">
		<div class="col-tn-12 col-sm-3">
			<img class="center-block" src="{img filename='innReachEncoreLogo.png'}" alt="{$innReachEncoreName} Logo" style="max-width: 100%">
		</div>
		<div class="col-tn-12 col-sm-9">
			<h2>In {$innReachEncoreName}</h2>
			Request items from other {$innReachEncoreName} libraries to be delivered to your local library for pickup.
		</div>
	</div>

	{if $prospectorResults}
		<div class="row" id="prospectorSearchResultsSection">
			<div class="col-tn-12">

				{foreach from=$prospectorResults item=prospectorResult name="recordLoop"}

					<div class="result">
						<h3 class="h4">
							<span class="result-index">{$smarty.foreach.recordLoop.iteration}.</span>&nbsp;
							<a class="result-title notranslate" href="{$prospectorResult.link}" rel="external" target="_blank">
								{$prospectorResult.title}
							</a>
						</h3>
						{if $prospectorResult.author}
							<div class="row">
								<div class="col-tn-12">by {$prospectorResult.author}</div>
							</div>
						{/if}
						{if $prospectorResult.pubDate}
							<div class="row">
								<div class="col-tn-12">Published: {$prospectorResult.pubDate}</div>
							</div>
						{/if}
					</div>

				{/foreach}

			</div>
		</div>
	{/if}

	<div class="row" id="prospectorLinkSection">
		<div class="col-tn-12">
			<br>
			<button class="btn btn-sm btn-info pull-right" onclick="window.open('{$prospectorLink}', 'child'); return false">See more results in {$innReachEncoreName}</button>
		</div>
	</div>

	<style>
		{literal}
		#prospectorSection,#prospectorSearchResultsSection {
			padding-top: 15px;
		}
		#prospectorLinkSection {
			padding-bottom: 15px;
		}
		{/literal}
	</style>
{/strip}
