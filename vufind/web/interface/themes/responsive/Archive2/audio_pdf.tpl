{* Audio + PDF: audio player above the PDF.js viewer. Metadata follows in wrapper.tpl. *}
<div class="archive-audio-pdf-audio">
	{include file="Archive2/audio.tpl"}
</div>
<div class="archive-audio-pdf-document">
	{if $iframe_src}
		{include file="Archive2/pdfjs.tpl"}
	{/if}
</div>
