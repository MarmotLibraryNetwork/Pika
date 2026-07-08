{strip}
	{* pika_related_link is displayed in the Explore More sidebar as Librarian Picks *}
	{if $externalLinks}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">External Link: </div>
			<div class="result-value col-sm-8">
				{foreach from=$externalLinks item=link}
					<div><a href="{$link.uri|escape}" target="_blank">{$link.title|escape}</a></div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $catalogLinks}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Catalog Link: </div>
			<div class="result-value col-sm-8">
				{foreach from=$catalogLinks item=link}
					<div><a href="{$link.uri|escape}" target="_blank">{$link.title|escape}</a></div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $furtherSiteLinks}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Further Site Info: </div>
			<div class="result-value col-sm-8">
				{foreach from=$furtherSiteLinks item=link}
					<div><a href="{$link.uri|escape}" target="_blank">{$link.title|escape}</a></div>
				{/foreach}
			</div>
		</div>
	{/if}
	{if $genealogyLinks}
		<div class="row archive-field-row">
			<div class="result-label col-sm-4">Genealogy Link: </div>
			<div class="result-value col-sm-8">
				{foreach from=$genealogyLinks item=link}
					<div><a href="{$link.uri|escape}" target="_blank">{$link.title|escape}</a></div>
				{/foreach}
			</div>
		</div>
	{/if}
{/strip}
