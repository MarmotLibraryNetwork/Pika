{* Compound Audio Viewer - Multiple audio objects with single player *}

{* Audio Player *}
<div style="margin-bottom: 30px;">
    <audio style="width:100%;" id="compound-audio-player" controls crossorigin="">
        {* Tracks will be dynamically loaded via JavaScript *}
    </audio>
    <div id="compound-vtt-text" class="archive-caption"
        style="height: 60px; background: #333; color: #fff; display:none; text-align:center; font-family:Helvetica, Arial, sans-serif; font-weight:bold; text-wrap:balance; padding: 10px; box-sizing: border-box;">
    </div>
</div>

{* Grid of Audio Items *}
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; margin-top: 20px;">
    {foreach from=$children item=child name=audioLoop}
        <div class="audio-item"
             data-audio-url="{$child.audioUrl}"
             data-audio-mime="{$child.audioMime}"
             data-audio-title="{$child.title|escape}"
             data-captions='{if $child.captions}{$child.captions|@json_encode}{else}[]{/if}'
             data-index="{$smarty.foreach.audioLoop.index}"
             style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 15px; transition: all 0.3s ease; background: #fff;">

            {* Thumbnail *}
            {if $child.thumbnailUrl}
                <div style="margin-bottom: 10px; text-align: center; overflow: hidden; border-radius: 4px; background: #f5f5f5;">
                    <img src="{$child.thumbnailUrl}"
                         alt="{$child.title|escape}"
                         style="width: 100%; height: 150px; object-fit: cover; display: block;">
                </div>
            {else}
                <div style="margin-bottom: 10px; text-align: center; overflow: hidden; border-radius: 4px; background: #f5f5f5; height: 150px; display: flex; align-items: center; justify-content: center;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="#ccc">
                        <path d="M12 3v9.28c-.47-.17-.97-.28-1.5-.28C8.01 12 6 14.01 6 16.5S8.01 21 10.5 21c2.31 0 4.2-1.75 4.45-4H15V6h4V3h-7z"/>
                    </svg>
                </div>
            {/if}

            {* Title *}
            <h4 style="margin: 0; font-size: 16px; font-weight: 600; color: #333; line-height: 1.4; min-height: 44px;">
                {$child.title}
            </h4>

            {* Active indicator *}
            <div class="active-indicator" style="margin-top: 10px; padding: 5px 10px; background: #666; color: #f1f1f1; border-radius: 4px; text-align: center; font-size: 14px; font-weight: 600; display: none;">
                ▶ Now Playing
            </div>
        </div>
    {/foreach}
</div>

{literal}
<script>
(function() {
    const player = document.getElementById('compound-audio-player');
    const vttText = document.getElementById('compound-vtt-text');
    const audioItems = document.querySelectorAll('.audio-item');
    let currentTrack = null;
    let currentCueListener = null;

    // Load audio track
    function loadAudioTrack(item, index) {
        const audioUrl = item.dataset.audioUrl;
        const audioMime = item.dataset.audioMime;
        const captions = JSON.parse(item.dataset.captions || '[]');

        // Store current playback position if switching tracks
        const wasPlaying = !player.paused;

        // Update player source
        player.src = audioUrl;
        player.type = audioMime;

        // Remove existing tracks
        while (player.firstChild) {
            player.removeChild(player.firstChild);
        }

        // Add caption tracks
        if (captions && captions.length > 0) {
            captions.forEach((caption, idx) => {
                const track = document.createElement('track');
                track.kind = 'captions';
                track.src = '/Archive/AJAX?method=fetchVtt&path=' + encodeURIComponent(caption.filePath);
                track.label = caption.langName || 'Captions';
                track.srclang = caption.langCode || 'en';
                if (idx === 0) {
                    track.default = true;
                }
                player.appendChild(track);
            });
        }

        // Load the audio
        player.load();

        // Setup caption display
        setupCaptions();

        // Update active states
        audioItems.forEach(i => {
            i.style.borderColor = '#ddd';
            i.style.boxShadow = 'none';
            i.querySelector('.active-indicator').style.display = 'none';
        });

        item.style.borderColor = '#666';
        item.style.boxShadow = '0 4px 12px rgba(102, 102, 102, .2)';
        item.querySelector('.active-indicator').style.display = 'block';

        // Resume playback if was playing
        if (wasPlaying) {
            player.play().catch(e => console.log('Playback prevented:', e));
        }

        currentTrack = index;
    }

    // Setup caption display
    function setupCaptions() {
        // Remove previous listener
        if (currentCueListener && player.textTracks[0]) {
            player.textTracks[0].removeEventListener('cuechange', currentCueListener);
        }

        // Setup new listener if captions exist
        if (player.textTracks && player.textTracks[0]) {
            player.textTracks[0].mode = 'showing';

            currentCueListener = function() {
                if (this.activeCues && this.activeCues.length > 0) {
                    vttText.innerText = this.activeCues[0].text;
                } else {
                    vttText.innerText = '';
                }
            };

            player.textTracks[0].addEventListener('cuechange', currentCueListener);
        }
    }

    // Event listeners for caption display
    player.addEventListener('play', function() {
        if (player.textTracks && player.textTracks.length > 0) {
            vttText.style.display = 'block';
        }
    });

    player.addEventListener('pause', function() {
        //vttText.style.display = 'none';
    });

    player.addEventListener('ended', function() {
        vttText.style.display = 'none';
        vttText.innerText = '';

        // Auto-play next track if available
        const nextIndex = currentTrack + 1;
        if (nextIndex < audioItems.length) {
            loadAudioTrack(audioItems[nextIndex], nextIndex);
        }
    });

    // Click handlers for audio items
    audioItems.forEach((item, index) => {
        item.addEventListener('click', function() {
            loadAudioTrack(item, index);
        });

        // Add hover effect
        item.addEventListener('mouseenter', function() {
            if (currentTrack !== index) {
                this.style.borderColor = '#aaa';
                this.style.boxShadow = '0 2px 8px rgba(170, 170, 170, .3)';
            }
        });

        item.addEventListener('mouseleave', function() {
            if (currentTrack !== index) {
                this.style.borderColor = '#ddd';
                this.style.boxShadow = 'none';
            }
        });
    });

    // Load first track by default
    if (audioItems.length > 0) {
        loadAudioTrack(audioItems[0], 0);
    }
})();
</script>
{/literal}
