{strip}
    <div class="col-xs-12">
        {* Search Navigation *}
        {include file="Archive/search-results-navigation.tpl"}
        <h1 role="heading" aria-level="1" class="h2">{$title}</h1>

        {if $can_view == false}
            {include file="Archive/noAccess.tpl"}
        {else}
            {* start content *}
            {if $videoThumbnailUrl}
                <div style="display: flex; justify-content: center">
                    <img src={$videoThumbnailUrl} style="margin: 0 auto">
                </div>
            {/if}
            <audio src="{$audioUrl}" type="{$audioMime}" style="width:100%;" id="archive-audio-player" controls>
                {if count($captions) >= 1}
                    {foreach from=$captions item=i}
                        <track kind="captions" src="/Archive/AJAX?method=fetchVtt&path={$i.filePath|escape:'url'}" label="{$i.langName}" srclang="{$i.langCode}" />
                    {/foreach}
                {/if}
            </audio>
            <div id="vtt-text" class="archive-caption" style="height: 60px; background: #333; color: #fff; display:none; text-align:center;font-family:Helvetica, Arial, sans-serif; font-weight:bold; text-wrap:balance"></div>
            {literal}
            <script>
                document.getElementById('archive-audio-player').textTracks[0].mode="showing";

                document.getElementById('archive-audio-player').addEventListener('play', function() {
                    document.getElementById('vtt-text').style.display="block";
                });

                document.getElementById('archive-audio-player').addEventListener('pause', function() {
                    document.getElementById('vtt-text').style.display="none";
                });

                document.getElementById('archive-audio-player').textTracks[0].addEventListener('cuechange', function() {
                    document.getElementById('vtt-text').innerText = this.activeCues[0].text;
                });
            </script>
            {/literal}
            <div id="download-options">
                {if $can_download}
                    <a class="btn btn-default" href="/Archive/{$pid}/DownloadOriginal">Download Original</a>
                {elseif (!$loggedIn && $allow_original_download)}
                    <a class="btn btn-default" onclick="return Pika.Account.followLinkIfLoggedIn(this)"
                        href="/Archive/{$pid}/DownloadOriginal">Log in to Download Original</a>
                {/if}
                {if $allowRequestsForArchiveMaterials}
                    <a class="btn btn-default" href="/Archive/RequestCopy?pid={$nid}">Request Copy</a> ` `
                {/if}
                {if $showClaimAuthorship}
                    <a class="btn btn-default" href="/Archive/ClaimAuthorship?pid={$nid}">Claim Authorship</a>
                {/if}
                {if $showFavorites == 1}
                    <button onclick="return Pika.Archive.showSaveToListForm(this, '{$nid|escape}');"
                        class="btn btn-default">{translate text='Add to favorites'}</button>
                {/if}
            </div>

            {include file="Archive2/metadata.tpl"}
        </div>
        {* end content *}
    {/if}
{/strip}