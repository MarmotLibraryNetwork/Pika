{strip}
<div id="listWidgetHelp">
	<h2 class="h3">List Widget Integration Notes</h2>
	<div class="well">
		<p>To integrate this widget into another site, insert an iFrame into your site with a source of :</p>
		<blockquote class="alert-info bold">{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}</blockquote>
		<p>
			<code style="white-space: normal">&lt;iframe src=&quot;{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}&quot;&nbsp;&nbsp;
				title=&quot;[Useful description of the embedded widget for text-only viewers]&quot;&nbsp;&nbsp;
				width=&quot;{$width}&quot; height=&quot;{$height}&quot;&nbsp;&nbsp;
				scrolling=&quot;{if $selectedStyle == "text-list"}yes{else}no{/if}&quot;&gt;&lt;/iframe&gt;
			</code>
		</p>
		<blockquote class="alert-info"><strong>Accessibility : </strong>
			The iframe needs a title attribute to meet accessibility standards. The iframe title provides text-only users with a good decision point for them to choose to either enter the iframe content or to move on to the next element in the page.&nbsp;
			(<a href="https://developer.mozilla.org/en-US/docs/Web/HTML/Element/iframe#accessibility_concerns">Developer documentation with more information.</a>)
		</blockquote>

		<p>Width and height can be adjusted as needed to fit within your site.</p>
		<blockquote class="alert-warning"> Note: Please avoid using percentages for the iframe width &amp; height as these values are not respected on iPads and other iOS devices & browsers.</blockquote>
		<blockquote class="alert-warning"> Note: Text Only List Widgets use the iframe's scrollbar.</blockquote>
		<blockquote class="alert-warning"> Recommend: Set iframe attribute frameborder="0" and put border any desired styling in your Style Sheet. The attribute frameborder is now deprecated.</blockquote>
	</div>
</div>

<h3 class="h4">Live Preview</h3>

<iframe src="{$url}/API/SearchAPI?method=getListWidget&id={$object->id}&reload=true" title="{$object->name}" width="{$width}" height="{$height}" scrolling="{if $selectedStyle == "text-list"}yes{else}no{/if}" >
	<p>Your browser does not support iframes. :( </p>
</iframe>
<hr>
<h2 class="h3">List Widget with Resizing Integration Notes</h2>
<div class="well">
	<p>
		To have a list widget which adjusts its height based on the html content within the list widget use the source url :
	</p>
	<blockquote class="alert-info">
	{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}<span class="bold">&resizeIframe=on</span>
	</blockquote>
	<p>
		Include the iframe tag and javascript tags in the site :
	</p>
	<p>
{/strip}
<code style="white-space: normal">
	&lt;iframe id=&quot;listWidget{$object->id}&quot;  onload=&quot;setWidgetSizing(this, 30)&quot;  src=&quot;{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}&amp;resizeIframe=on&quot;&nbsp;&nbsp;
	title=&quot;[Useful description of the embedded widget for text-only viewers]&quot;&nbsp;&nbsp;
	width=&quot;{$width}&quot;  scrolling=&quot;{if $selectedStyle == "text-list"}yes{else}no{/if}&quot;&gt;&lt;/iframe&gt;
</code>
{literal}
<code style="white-space: pre">

&lt;!-- Horizontal Resizing : Based on Iframe Content --&gt;

&lt;script src=&quot;{/literal}{$url}{literal}/js/iframeResizer/iframeResizer.min.js&quot;&gt;&lt;/script&gt;
&lt;script&gt;
	jQuery(&quot;#listWidget{/literal}{$object->id}{literal}&quot;).iFrameResize();
&lt;/script&gt;

&lt;!-- Vertical Resizing : When Iframe is larger than viewport width,
	resize to 100% of browser width - 2 * padding (in px) --&gt;

&lt;script&gt;
	setWidgetSizing = function(iframe, OutsidePadding){
		originalWidth = jQuery(iframe).width();
		wasResized = false;
		jQuery(window).resize(function(){
			resizeWidgetWidth(iframe, OutsidePadding);
		}).resize();
	};

	resizeWidgetWidth = function(iframe, padding){
		if (padding == undefined) padding = 4;
		var viewPortWidth = jQuery(window).width(),
			newWidth = viewPortWidth - 2*padding,
			width = jQuery(iframe).width();
		if (width > newWidth) {
			wasResized = true;
			return jQuery(iframe).width(newWidth);
		}
		if (wasResized && originalWidth + 2*padding < viewPortWidth){
			wasResized = false;
			return jQuery(iframe).width(originalWidth);
		}
	};
{/literal}
&lt;/script&gt;
</code>
{strip}
</p>
<blockquote class="alert-warning">
	This requires that the site displaying the list widget have the jQuery library.
</blockquote>

	<blockquote class="alert-info"><strong>Accessibility : </strong>
		The iframe needs a title attribute to meet accessibility standards. The iframe title provides text-only users with a good decision point for them to choose to either enter the iframe content or to move on to the next element in the page.&nbsp;
		(<a href="https://developer.mozilla.org/en-US/docs/Web/HTML/Element/iframe#accessibility_concerns">Developer documentation with more information.</a>)
	</blockquote>

</div>
<h3 class="h4">Live Preview</h3>
<iframe id="listWidget{$object->id}" title="{$object->name}" onload="setWidgetSizing(this, 30)" src="{$url}/API/SearchAPI?method=getListWidget&id={$object->id}&resizeIframe=on&reload=true" width="{$width}" {*height="{$height}"*} scrolling="{if $selectedStyle == "text-list"}yes{else}no{/if}">
	<p>Your browser does not support iframes. :( </p>
</iframe>

	{* Iframe dynamic Height Re-sizing script *}
	<script src="/js/iframeResizer/iframeResizer.min.js"></script>
	{/strip}

	{* Width Resizing Code *}
<script>
	jQuery('#listWidget{$object->id}').iFrameResize();
</script>

{literal}
	<script>
		setWidgetSizing = function(iframe, OutsidePadding){
			originalWidth = jQuery(iframe).width();
			wasResized = false;
			jQuery(window).resize(function(){
				resizeWidgetWidth(iframe, OutsidePadding);
			}).resize();
		};

	resizeWidgetWidth = function(iframe, padding){
		if (padding == undefined) padding = 4;
		var viewPortWidth = jQuery(window).width(),
				newWidth = viewPortWidth - 2*padding,
				width = jQuery(iframe).width();
		if (width > newWidth) {
			wasResized = true;
			return jQuery(iframe).width(newWidth);
		}
		if (wasResized && originalWidth + 2*padding < viewPortWidth){
			wasResized = false;
			return jQuery(iframe).width(originalWidth);
		}
	};
</script>
{/literal}