{if $videoThumbnailUrl}
    <div class="archive-audio-poster-wrapper">
    <div>
        <img src={$videoThumbnailUrl} class="archive-audio-poster-image" alt="Audio poster image for {$title}">
    </div>
    </div>
{/if}
{* Temporary fix: block the right-click context menu on all browsers to hide "Save Audio As", since controlslist="nodownload" only works in Chromium. *}
<audio src="{$audioUrl}" type="{$audioMime}" controls controlslist="nodownload" class="archive-audio-player" id="archive-audio-player" oncontextmenu="return false;">
    {if count($captions) >= 1}
        {foreach from=$captions item=i}
            <track kind="captions" src="/Archive2/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}&nid={$nid|intval}" label="{$i.langName}"
                srclang="{$i.langCode}" />
        {/foreach}
    {/if}
</audio>
<div id="vtt-text" class="archive-caption">
</div>
{literal}
    <script>
        (function() {
            var player = document.getElementById('archive-audio-player');
            var box    = document.getElementById('vtt-text');
            var track  = player.textTracks && player.textTracks[0];

            if (!track) return;

            track.mode = 'showing';

            track.addEventListener('cuechange', function() {
                if (this.activeCues && this.activeCues.length > 0) {
                    box.innerText = this.activeCues[0].text;
                    box.style.display = 'block';
                } else {
                    //box.style.display = 'none';
                }
            });

            // Detect when the user toggles captions off via the native browser controls.
            // There is no dedicated event for track mode changes, so timeupdate is used.
            player.addEventListener('timeupdate', function() {
                if (track.mode !== 'showing') {
                    box.style.display = 'none';
                }
            });
        }());
    </script>
{/literal}