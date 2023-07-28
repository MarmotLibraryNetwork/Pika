{strip}
<div id="listWidgetHelp">
	<h3>List Widget</h3>
	<h4>Integration notes</h4>
	<div class="well">
		<p>To integrate this widget into another site, insert an iFrame into your site with a source of :</p>
		<blockquote class="alert-info" style="font-weight: bold;">{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}</blockquote>
		<p>
			<code style="white-space: normal">&lt;iframe src=&quot;{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}&quot;
				width=&quot;{$width}&quot; height=&quot;{$height}&quot;
				scrolling=&quot;{if $selectedStyle == "text-list"}yes{else}no{/if}&quot;&gt;&lt;/iframe&gt;
			</code>
		</p>
		<p>Width and height can be adjusted as needed to fit within your site.</p>
		<blockquote class="alert-warning"> Note: Please avoid using percentages for the iframe width &amp; height as these values are not respected on iPads and other iOS devices & browsers.</blockquote>
		<blockquote class="alert-warning"> Note: Text Only List Widgets use the iframe's scrollbar.</blockquote>
		<blockquote class="alert-warning"> Recommend: set iframe attribute frameborder="0" and put border any desired styling in your Style Sheet.</blockquote>
	</div>
</div>

<h4>Live Preview</h4>

<iframe src="{$url}/API/SearchAPI?method=getListWidget&id={$object->id}&reload=true" width="{$width}" height="{$height}" scrolling="{if $selectedStyle == "text-list"}yes{else}no{/if}" >
	<p>Your browser does not support iframes. :( </p>
</iframe>
<hr>
<h3>List Widget with Resizing</h3>
<h4>Integration notes</h4>
<div class="well">
	<p>
		To have a list widget which adjusts it's height based on the html content within the list widget use the source url :
	</p>
	<blockquote class="alert-info">
	{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}<span style="font-weight: bold;">&resizeIframe=on</span>
	</blockquote>
	<p>
		Include the iframe tag and javascript tags in the site :
	</p>
	<p>
{/strip}
<code style="white-space: normal">
	&lt;iframe id=&quot;listWidget{$object->id}&quot;  onload=&quot;setWidgetSizing(this, 30)&quot;  src=&quot;{$url}/API/SearchAPI?method=getListWidget&amp;id={$object->id}&amp;resizeIframe=on&quot;
	width=&quot;{$width}&quot; scrolling=&quot;{if $selectedStyle == "text-list"}yes{else}no{/if}&quot;&gt;&lt;/iframe&gt;
</code>
{literal}
<code style="white-space: pre">

&lt;!-- Horizontal Resizing : Based on Iframe Content --&gt;

&lt;script type=&quot;text/javascript&quot; src=&quot;{/literal}{$url}{literal}/js/iframeResizer/iframeResizer.min.js&quot;&gt;&lt;/script&gt;
&lt;script type=&quot;text/javascript&quot;&gt;
	jQuery(&quot;#listWidget{/literal}{$object->id}{literal}&quot;).iFrameResize();
&lt;/script&gt;

&lt;!-- Vertical Resizing : When Iframe is larger than viewport width,
	resize to 100% of browser width - 2 * padding (in px) --&gt;

&lt;script type=&quot;text/javascript&quot;&gt;
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

</div>
<h4>Live Preview</h4>
<iframe id="listWidget{$object->id}" onload="setWidgetSizing(this, 30)" src="{$url}/API/SearchAPI?method=getListWidget&id={$object->id}&resizeIframe=on&reload=true" width="{$width}" {*height="{$height}"*} scrolling="{if $selectedStyle == "text-list"}yes{else}no{/if}">
<p>Your browser does not support iframes. :( </p>
	</iframe>

	{* Iframe dynamic Height Re-sizing script *}
	<script type="text/javascript" src="/js/iframeResizer/iframeResizer.min.js"></script>
	{/strip}

	{* Width Resizing Code *}
<script type="text/javascript">
	jQuery('#listWidget{$object->id}').iFrameResize();
</script>

{literal}
	<script type="text/javascript">
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