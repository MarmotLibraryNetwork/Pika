{strip}
	<a href="http://www.pinterest.com/pin/create/button/?url={$urlToShare}{if $coverUrl}&media={$coverUrl|escape:'url'}{/if}{if $description}&description={$description}{/if}"
	   data-pin-custom="true"
	   {if $coverUrl}
	   data-pin-do="buttonPin"
	   data-pin-media="{$coverUrl}"
	   {/if}
	   target="_blank"
	   style="cursor:pointer;"
	   title="Pin on Pinterest">
		<img src="{img filename='pinterest-icon.png'}" alt="Pin on Pinterest">
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