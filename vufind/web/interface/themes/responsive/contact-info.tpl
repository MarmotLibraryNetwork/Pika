{strip}
	{if $twitterLink || $facebookLink || $generalContactLink || $youtubeLink || $instagramLink || $goodreadsLink}
		<span id="connect-with-us-label" class="large">CONNECT WITH US</span>
		{if $twitterLink}
			<a href="{$twitterLink}" class="connect-icon"><img src="{img filename='twitter.png'}" alt="Contact the library on Twitter" class="img-rounded"></a>
		{/if}
		{if $facebookLink}
			<a href="{$facebookLink}" class="connect-icon"><img src="{img filename='facebook.png'}" alt="Contact the library on Facebook" class="img-rounded"></a>
		{/if}
		{if $youtubeLink}
			<a href="{$youtubeLink}" class="connect-icon"><img src="{img filename='youtube.png'}" alt="Contact the library on Youtube"  class="img-rounded"></a>
		{/if}
		{if $instagramLink}
			<a href="{$instagramLink}" class="connect-icon"><img src="{img filename='instagram.png'}" alt="Contact the library on Instangram" class="img-rounded"></a>
		{/if}
		{if $goodreadsLink}
			<a href="{$goodreadsLink}" class="connect-icon"><img src="{img filename='goodreads.png'}" alt="Contact the library on GoodReads" class="img-rounded"></a>
		{/if}
		{if $generalContactLink}
			<a href="{$generalContactLink}" class="connect-icon"><img src="{img filename='email-contact.png'}" alt="Contact the library via email" class="img-rounded"></a>
		{/if}
	{/if}
{/strip}