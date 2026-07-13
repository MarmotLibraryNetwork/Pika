{strip}
<div class="navbar navbar-static-bottom">
	<div class="navbar-inner">
		<div class="row">
			<div class="col-12 {if $showPikaLogo}col-md-4{else}col-md-7{/if} text-start float-start" id="install-info">
				{include file="footer-install-info.tpl"}
			</div>
			{if $showPikaLogo}
			<div class="col-12 col-md-3 text-center float-start">
				<a href="http://marmot.org/pika" title="Proud Pika Partner">
					<img id="footer-pika-logo" src="{img filename='pika-logo.png'}" alt="Proud Pika Partner" style="max-width: 100%; max-height: 80px;">
				</a>
			</div>
			{/if}
			<div class="col-12 col-md-4 text-end float-end" id="connect-with-us-info" role="region" aria-label="Contact Information">
				{include file="contact-info.tpl"}
			</div>
		</div>
		{include file="footer-indexing-info.tpl"}
	</div>
</div>
{/strip}
