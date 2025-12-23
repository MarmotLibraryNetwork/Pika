<video width="100%" controls poster="{$posterUrl}" id="video-player">
    <source src="{$videoUrl}" type="{$videoMime}">
    {if count($captions) >= 1}
        {foreach from=$captions item=i}
            <track kind="captions" src="/Archive/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}" label="{$i.langName}" srclang="{$i.langCode}" />
        {/foreach}
    {/if}
</video>