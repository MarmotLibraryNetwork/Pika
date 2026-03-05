{* Compound Video Viewer - Multiple video objects with single player *}

{* Video Player *}
<div style="margin-bottom: 30px;">
    <video width="100%" controls id="compound-video-player" crossorigin="anonymous" style="background: #000;">
        {* Source and tracks will be dynamically loaded via JavaScript *}
    </video>
</div>

{* Grid of Video Items *}
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 20px; margin-top: 20px;">
    {foreach from=$children item=child}
        <div class="video-item"
             data-video-url="{$child.videoUrl}"
             data-video-mime="{$child.videoMime}"
             data-video-title="{$child.title|escape}"
             data-poster-url="{$child.posterUrl}"
             data-captions='{if $child.captions}{$child.captions|@json_encode}{else}[]{/if}'
             data-index="{$child@index}"
             style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 15px; transition: all 0.3s ease; background: #fff;">

            {* Thumbnail/Poster *}
            {if $child.posterUrl}
                <div style="margin-bottom: 10px; text-align: center; overflow: hidden; border-radius: 4px; background: #000; position: relative;">
                    <img src="{$child.posterUrl}"
                         alt="{$child.title|escape}"
                         style="width: 100%; height: 180px; object-fit: cover; display: block;">
                    {* Play icon overlay *}
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none;">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="rgba(255,255,255,0.9)">
                            <circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)"/>
                            <path d="M8 5v14l11-7z" fill="rgba(255,255,255,0.9)"/>
                        </svg>
                    </div>
                </div>
            {else}
                <div style="margin-bottom: 10px; text-align: center; overflow: hidden; border-radius: 4px; background: #000; height: 180px; display: flex; align-items: center; justify-content: center; position: relative;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="#666">
                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
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
    const player = document.getElementById('compound-video-player');
    const videoItems = document.querySelectorAll('.video-item');
    let currentTrack = null;

    // Load video track
    function loadVideoTrack(item, index) {
        const videoUrl = item.dataset.videoUrl;
        const videoMime = item.dataset.videoMime;
        const posterUrl = item.dataset.posterUrl;
        const captions = JSON.parse(item.dataset.captions || '[]');

        // Store current playback position if switching tracks
        const wasPlaying = !player.paused;

        // Update player source
        player.src = videoUrl;
        player.type = videoMime;
        if (posterUrl) {
            player.poster = posterUrl;
        }

        // Remove existing tracks
        while (player.firstChild) {
            player.removeChild(player.firstChild);
        }

        // Add source element
        const source = document.createElement('source');
        source.src = videoUrl;
        source.type = videoMime;
        player.appendChild(source);

        // Add caption tracks
        if (captions && captions.length > 0) {
            captions.forEach((caption, idx) => {
                const track = document.createElement('track');
                track.kind = 'captions';
                track.src = caption.fileUrl || '';
                track.label = caption.langName || 'Captions';
                track.srclang = caption.langCode || 'en';
                if (idx === 0) {
                    track.default = true;
                }
                player.appendChild(track);
            });
        }

        // Load the video
        player.load();

        // Update active states
        videoItems.forEach(i => {
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

    // Auto-play next track when video ends
    player.addEventListener('ended', function() {
        const nextIndex = currentTrack + 1;
        if (nextIndex < videoItems.length) {
            loadVideoTrack(videoItems[nextIndex], nextIndex);
        }
    });

    // Click handlers for video items
    videoItems.forEach((item, index) => {
        item.addEventListener('click', function() {
            loadVideoTrack(item, index);
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
    if (videoItems.length > 0) {
        loadVideoTrack(videoItems[0], 0);
    }
})();
</script>
{/literal}
