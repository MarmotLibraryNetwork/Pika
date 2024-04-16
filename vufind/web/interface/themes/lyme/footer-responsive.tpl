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
				<a href="http://lioninc.org" title="Member of Libraries Online Incorporated">
					<img src="/interface/themes/lion/images/lion_logo.png" alt="Member of Libraries Online Incorporated" style="max-width: 100%; max-height:130px; margin-left:20px; margin-right:20px">
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
