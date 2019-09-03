{strip}
	<![CDATA[
	<div class="row" id="prospectorSection">
		<div class="col-tn-12 col-sm-4">
			<img class="center-block" src="{img filename='innReachEncoreLogo.png'}" style="max-width: 100%">
		</div>
		<div class="col-tn-12 col-sm-8">
			<h2>In {$innReachEncoreName}</h2>
			Request items from other {$innReachEncoreName} libraries to be delivered to your local library for pickup.
		</div>
	</div>

	{if $prospectorResults}
		<div class="row" id="prospectorSearchResultsSection">
			<div class="col-tn-12">

				<div class="striped">
					{foreach from=$prospectorResults item=prospectorResult}
						<div class="result">
							<div class="resultItemLine1">
								<a class="title" href='{$prospectorResult.link}' rel="external" onclick="window.open(this.href, 'child'); return false">
									{$prospectorResult.title}
								</a>
							</div>
							<div class="resultItemLine2">by {$prospectorResult.author} Published {$prospectorResult.pubDate}</div>
						</div>
					{/foreach}
				</div>

			</div>
		</div>
	{/if}

	<div class="row" id="prospectorLinkSection">
		<div class="col-tn-12">
			<br>
			<button class="btn btn-sm btn-info pull-right" onclick="window.open('{$prospectorLink}', 'child'); return false">See more results in {$innReachEncoreName}</button>
		</div>
	</div>

	<style type="text/css">
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
