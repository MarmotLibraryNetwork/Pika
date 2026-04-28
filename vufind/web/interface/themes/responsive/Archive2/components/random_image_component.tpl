{strip}
<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div class="archiveComponent horizontalComponent">
		<div class="archiveComponentBody">
			<div class="archiveComponentBox">
				<div class="archiveComponentHeader">Random Image</div>
				<div class="archiveComponentRandomImage row">
					{if $randomObject}
					<figure>
						<a href="{$randomObject.url}">
							{if $randomObject.thumbnail}
							<img src="{$randomObject.thumbnail}" alt="{$randomObject.title|escape}">
							{/if}
							<figcaption class="explore-more-category-title">
								<strong>{$randomObject.title|truncate:120}</strong>
							</figcaption>
						</a>
					</figure>
					{/if}
				</div>
			</div>
		</div>
	</div>
</div>
{/strip}
