<video width="100%" controls poster="{$posterUrl}" id="video-player" crossorigin="anonymous">
    <source src="{$videoUrl}" type="{$videoMime}" >
    {if count($captions) >= 1}
        {foreach from=$captions item=i}
            <track kind="captions" src="{$i.fileUrl}" label="{$i.langName}" srclang="{$i.langCode}" />
        {/foreach}
    {/if}
</video>