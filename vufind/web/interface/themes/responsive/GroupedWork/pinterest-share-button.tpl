{strip}
	<a href="http://www.pinterest.com/pin/create/button/?url={$urlToShare}{if $coverUrl}&media={$coverUrl|escape:'url'}{/if}{if $description}&description={$description|escape:'url'}{/if}" {* keep space between attributes *}
		 data-pin-custom="true" {* keep space between attributes *}
		 {if $coverUrl}
			 data-pin-do="buttonPin" {* keep space between attributes *}
			 data-pin-media="{$coverUrl}" {* keep space between attributes *}
		 {/if}
		target="_blank" {* keep space between attributes *}
		style="cursor:pointer;" {* keep space between attributes *}
		title="Pin on Pinterest"
		>
		{if $linkText}{$linkText}{/if}
		<img {if $imgClass}class="{$imgClass}"{/if} src="{img filename='pinterest-icon.png'}" alt="Pin on Pinterest">
	</a>
	{if !$pinterestJS}
		{* load the javascript only once per page as needed *}
		<script async defer src="//assets.pinterest.com/js/pinit.js"
			{if $debugJs}
				{* log pinterest errors to browser console *}
				data-pin-error="1"
			{/if}

		></script>
		{assign var="pinterestJS" value=true}
	{/if}


{/strip}