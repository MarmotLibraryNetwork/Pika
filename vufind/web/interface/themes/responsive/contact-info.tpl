{strip}
	{if $twitterLink || $facebookLink || $generalContactLink || $youtubeLink || $instagramLink || $goodreadsLink}
		<div class="row">
			<div class="col-tn-12">
		<span id="connect-with-us-label" class="large">CONNECT WITH US</span>
		{if $twitterLink}
			<a href="{$twitterLink}" class="connect-icon" title="Contact the library on X (twitter)"><img src="{img filename='twitter.png'}" alt="Contact the library on Twitter" class="img-rounded"></a>
		{/if}
		{if $facebookLink}
			<a href="{$facebookLink}" class="connect-icon" title="Contact the library on facebook"><img src="{img filename='facebook.png'}" alt="Contact the library on Facebook" class="img-rounded"></a>
		{/if}
		{if $youtubeLink}
			<a href="{$youtubeLink}" class="connect-icon" title="Contact the library on youtube"><img src="{img filename='youtube.png'}" alt="Contact the library on Youtube"  class="img-rounded"></a>
		{/if}
		{if $instagramLink}
			<a href="{$instagramLink}" class="connect-icon" title="Contact the library on instagram"><img src="{img filename='instagram.png'}" alt="Contact the library on Instagram" class="img-rounded"></a>
		{/if}
		{if $goodreadsLink}
			<a href="{$goodreadsLink}" class="connect-icon" title="Contact the library on goodreads"><img src="{img filename='goodreads.png'}" alt="Contact the library on GoodReads" class="img-rounded"></a>
		{/if}
		{if $generalContactLink}
			<a href="{$generalContactLink}" class="connect-icon" title="Contact the library via email "><img src="{img filename='email-contact.png'}" alt="Contact the library via email" class="img-rounded"></a>
		{/if}
			</div>
		</div>
	{/if}
	<div class="row" style="margin-top: 15px">
			<div class="col-tn-12 text-right pull-right" id="ReportAccessibilityIssue" aria-label="report accessibility issue">
				<a href="/Help/AccessibilityReport" title="issue reporting">Report Accessibility Issue</a>
			</div>
	</div>
{/strip}