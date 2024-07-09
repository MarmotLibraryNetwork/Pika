{if $showQRCode}
	{strip}
		<h3{* class="h4"*}>QR Code</h3>
		<div id="record-qr-code" class="text-center hidden-xs visible-md">
		<figure>
			<img src="{$recordDriver->getQRCodeUrl()}" alt="QR Code for {if $recordDriver->getTitle()}&quot;{$recordDriver->getTitle()|escape}&quot;{else}title{/if}.">
				<figcaption>To view this page on your smartphone, scan this image in your smartphone's camera app.</figcaption>
			</figure>
		</div>
	{/strip}
{/if}
