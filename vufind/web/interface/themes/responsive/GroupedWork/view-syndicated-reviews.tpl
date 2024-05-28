{strip}
	{foreach from=$syndicatedReviews item=providerList key=provider}
		{foreach from=$providerList item=review}
			{if $review.Content}
			<div class='review'>
				{if $review.Source}
					<h3 class="reviewSource h4">{$review.Source}</h3>
				{/if}
				<div>
				<p class="reviewContent">{$review.Content}</p>
				<div class='reviewCopyright'><small>{$review.Copyright}</small></div>
				{if $provider == "syndetics"}
					<div class='reviewProvider'><small>{translate text="Powered by Syndetics"}</small></div>
				{elseif $provider == "contentcafe"}
					<div class='reviewProvider'><small>{translate text="Powered by Content Cafe"}</small></div>
				{/if}
			</div>
			{/if}
			</div>
		{/foreach}
	{foreachelse}
		<p>No syndicated reviews currently exist.</p>
	{/foreach}
{/strip}