{strip}
	{if $showEmailThis == 1 || $showShareOnExternalSites == 1}
	<div class="share-tools">
		<span id="share-tools-label-{$recordDriver->getPermanentId()|escape}" class="share-tools-label hidden-inline-xs">SHARE</span>
		<ul aria-labelledby="share-tools-label-{$recordDriver->getPermanentId()|escape}" class="share-tools-list list-inline">
		{if false && $showTextThis == 1}
			<li>
				<a href="#" onclick="return Pika.GroupedWork.showSmsForm(this, '{$recordDriver->getPermanentId()|escape:"url"}')" title="Share via text message">
					<img src="{img filename='sms-icon.png'}" alt="Text This">
				</a>
			</li>
		{/if}
		{if $showEmailThis == 1}
			<li>
				<a href="#" onclick="return Pika.GroupedWork.showEmailForm(this, '{$recordDriver->getPermanentId()|escape:"url"}')" title="Share via e-mail">
					<img src="{img filename='email-icon.png'}" alt="E-mail this">
				</a>
			</li>
		{/if}
		{if $showShareOnExternalSites}
			<li>
				<a href="https://bsky.app/intent/compose?text={$recordDriver->getTitle()|escape:'url'}%20{$url}/GroupedWork/{$recordDriver->getPermanentId()}/Home" target="_blank" title="Share on Bluesky">
					{include file="images/bluesky-svg.tpl"}
				</a>
			</li>
			<li>
				<a href="http://www.facebook.com/sharer/sharer.php?u={$url}/{$recordDriver->getLinkUrl()|escape:'url'}" target="_blank" title="Share on Facebook">
					<img src="{img filename='facebook-icon.png'}" alt="Share on Facebook">
				</a>
			</li>
			<li>
				{include file="GroupedWork/pinterest-share-button.tpl" urlToShare=$url|cat:"/"|cat:$recordDriver->getLinkUrl() coverUrl=$recordDriver->getBookcoverUrl('large', true) description="Read at $homeLibrary"}
			</li>
			<li>
				<a href="https://x.com/compose/tweet?text={$recordDriver->getTitle()|escape:'url'}+{$url}/GroupedWork/{$recordDriver->getPermanentId()}/Home" target="_blank" title="Share on X">
					<img class="x-twit" src="{img filename='x-icon.png'}" alt="Share on X">
				</a>
			</li>
		{/if}
		</ul>
	</div>
	{/if}
{/strip}