<video width="100%" controls controlslist="nodownload" poster="{$posterUrl}" id="video-player" crossorigin="anonymous" oncontextmenu="return false;">
	{* controlslist="nodownload" only works on Chromium browsers; Firefox and Safari don't support it.*}
	{* Disabling the right click context menu is the only way to prevent the "Save Video as" option in those browsers. *}
	<source src="{$videoUrl}" type="{$videoMime}" >
	{if count($captions) >= 1}
		{foreach from=$captions item=i}
			<track kind="captions" src="{$i.fileUrl}" label="{$i.langName}" srclang="{$i.langCode}" />
		{/foreach}
	{/if}
</video>