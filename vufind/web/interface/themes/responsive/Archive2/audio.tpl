{if $videoThumbnailUrl}
    <div style="display: flex; justify-content: center">
        <img src={$videoThumbnailUrl} style="margin: 0 auto">
    </div>
{/if}
<audio src="{$audioUrl}" type="{$audioMime}" style="width:100%;" id="archive-audio-player" controls>
    {if count($captions) >= 1}
        {foreach from=$captions item=i}
            <track kind="captions" src="/Archive/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}" label="{$i.langName}"
                srclang="{$i.langCode}" />
        {/foreach}
    {/if}
</audio>
<div id="vtt-text" class="archive-caption"
    style="height: 60px; background: #333; color: #fff; display:none; text-align:center; font-family:Helvetica, Arial, sans-serif; font-weight:bold; text-wrap:balance">
</div>
{literal}
    <script>
        document.getElementById('archive-audio-player').textTracks[0].mode = "showing";

        document.getElementById('archive-audio-player').addEventListener('play', function() {
            document.getElementById('vtt-text').style.display = "block";
        });

        document.getElementById('archive-audio-player').addEventListener('pause', function() {
            //document.getElementById('vtt-text').style.display = "none";
        });

        document.getElementById('archive-audio-player').textTracks[0].addEventListener('cuechange', function() {
            document.getElementById('vtt-text').innerText = this.activeCues[0].text;
        });
    </script>
{/literal}