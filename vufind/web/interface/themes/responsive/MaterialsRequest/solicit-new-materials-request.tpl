{strip}
	<div class="solicit-new-materials-request">
		<h2>Didn't find it?</h2>
      {if $externalMaterialsRequestUrl}
				<p>
					Can't find what you are looking for? <a href="{$externalMaterialsRequestUrl}">{translate text='Suggest a purchase'}</a>.
				</p>
      {else}
				<p>
					Can't find what you are looking for? <a href="/MaterialsRequest/NewRequest{if !empty($lookfor)}?lookfor={$lookfor}&basicType={$searchIndex}{/if}"
									onclick="return Pika.Account.followLinkIfLoggedIn(this);">{translate text='Suggest a purchase'}</a>.
				</p>
      {/if}
	</div>
{/strip}