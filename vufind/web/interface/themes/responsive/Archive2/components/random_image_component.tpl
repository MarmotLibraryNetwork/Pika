{strip}
{*<div class="archiveComponentContainer nopadding col-sm-12 col-md-6">
	<div class="archiveComponent horizontalComponent">
		<div class="archiveComponentBody">
			<div class="archiveComponentBox">
				<div class="archiveComponentHeader">Random Image</div>
				<div class="archiveComponentRandomImage row">*}
					{if $randomObject}
					<figure>
						<a href="{$randomObject.url}">
							{if $randomObject.thumbnail}
							<img src="{$randomObject.thumbnail}" alt="{$randomObject.title|escape}" class="img-responsive thumbnail" style="object-fit: contain; margin: 0 auto; max-width: 250px; max-height: 250px;">
							{/if}
							<figcaption class="explore-more-category-title" style="text-align: center">
								<strong>{$randomObject.title|truncate:120}</strong>
							</figcaption>
						</a>
					</figure>
					{/if}
				{*</div>
			</div>
		</div>
	</div>
</div>*}
{/strip}
