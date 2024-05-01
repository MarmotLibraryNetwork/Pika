{strip}
	<div class="row">
		<div class="col-tn-12" id="header-links">
			{foreach from=$topLinks item=link}
				{if (!empty($link->url) && !empty($link->linkText))}
					<div class="header-link-wrapper">
						<a href="{$link->url}" class="library-header-link">{$link->linkText}</a>
					</div>
				{/if}
			{/foreach}
		</div>
	</div>
{/strip}