{* Video + PDF: video player above the PDF.js viewer. Metadata follows in wrapper.tpl. *}
<div class="archive-video-pdf-video">
	{include file="Archive2/video.tpl"}
</div>
<br />
<div class="archive-video-pdf-document">
	{if $iframe_src}
		{include file="Archive2/pdfjs.tpl"}
	{/if}
</div>
