{strip}
<div class="navbar navbar-static-bottom">
	<div class="navbar-inner">
		<div class="row">
			<div class="col-tn-12 col-sm-4 text-right pull-right" id="connect-with-us-info" role="region" aria-label="Contact Information">
				{include file="contact-info.tpl"}
			</div>
			<div class="col-tn-12 col-sm-4 text-left pull-left" id="install-info">
				{include file="footer-install-info.tpl"}
			</div>
			<div class="col-tn-12 col-sm-4 text-center pull-left">
				<a href="https://www.littletongov.org/" title="The City of Littleton, CO.">
					<img src="/interface/themes/bemis/images/littleton_logo.png" alt="The City of Littleton, CO." style="max-width: 100%; max-height:140px; margin-left:20px; margin-right:20px">
				</a>
				{if $showPikaLogo}
					<a href="http://marmot.org/pika-discovery/about-pika" title="Proud Pika Partner">
						<img id="footer-pika-logo" src="{img filename='pika-logo.png'}" alt="Proud Pika Partner" style="max-width: 100%; max-height: 80px;">
					</a>
				{/if}
			</div>
		</div>
		{include file="footer-indexing-info.tpl"}
	</div>
</div>
{/strip}
