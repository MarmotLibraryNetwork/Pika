{if $useCompoundAudio}
    {* Use compound audio viewer for all-audio compound objects *}
    {include file="Archive2/audio_compound.tpl" children=$audioChildren}
{elseif $useCompoundVideo}
    {* Use compound video viewer for all-video compound objects *}
    {include file="Archive2/video_compound.tpl" children=$videoChildren}
{else}
    {* Use individual viewers for each child *}
    {foreach from=$children item=child}
        <div class="child-object">
            {* Dynamically include the appropriate viewer helper template *}
            {* <p>DEBUG - Viewer: [{$child.viewer}] Model: [{$child.objectModel}]</p> *}
            <h4>{$child.title}</h4>
            {if $child.viewer == 'open_seadragon'}
                {include file="Archive2/compound_helpers/open_seadragon_helper.tpl" childMediaObject=$child.mediaObject}
            {elseif $child.viewer == 'video'}
                {include file="Archive2/compound_helpers/video_helper.tpl" childMediaObject=$child.mediaObject}
            {elseif $child.viewer == 'audio'}
                {include file="Archive2/compound_helpers/audio_helper.tpl" childMediaObject=$child.mediaObject}
            {elseif $child.viewer == 'pdfjs'}
                {include file="Archive2/compound_helpers/pdfjs_helper.tpl" childMediaObject=$child.mediaObject}
            {elseif $child.viewer == 'mirador'}
                {include file="Archive2/compound_helpers/mirador_helper.tpl" childMediaObject=$child.mediaObject}
            {else}
                <p>Viewer not supported: {$child.viewer}</p>
            {/if}
        </div>
    {/foreach}
{/if}
