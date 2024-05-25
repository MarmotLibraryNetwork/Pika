{strip}
	{* This template gets loaded into tableofContentsPlaceHolder via AJAX *}
	<div class="tableOfContents">
		{if !empty($tocData)}
			<ol class="list-unstyled">
		{foreach from=$tocData item=entry}
			{if $entry.label}
				<li class="tocEntry">
					<span class="tocLabel">{$entry.label} </span>
					<span class="tocTitle">{$entry.title} </span>
					<span class="tocPage">{$entry.page}</span>
				</li>
			{else}
				<li>
					<span class="trackNumber">{$entry.number} </span>
					<span class="trackName">{$entry.name}</span>
				</li>
			{/if}
		{/foreach}
			</ol>
		{/if}
	</div>
{/strip}