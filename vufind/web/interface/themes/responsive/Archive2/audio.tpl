{if $videoThumbnailUrl}
    <div style="display: flex; justify-content: center">
    <div>
        <img src={$videoThumbnailUrl} style="object-fit: contain; max-width: 600px; max-height: 600px;" alt="Audio poster image for {$title}">
    </div>
    </div>
{/if}
<audio src="{$audioUrl}" type="{$audioMime}" controls controlslist="nodownload" style="width:100%;" id="archive-audio-player" controls>
    {if count($captions) >= 1}
        {foreach from=$captions item=i}
            <track kind="captions" src="/Archive2/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}" label="{$i.langName}"
                srclang="{$i.langCode}" />
        {/foreach}
    {/if}
</audio>
<div id="vtt-text" class="archive-caption"
    style="height: 60px; background: #333; color: #fff; display:none; text-align:center; font-family:Helvetica, Arial, sans-serif; font-weight:bold; text-wrap:balance">
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