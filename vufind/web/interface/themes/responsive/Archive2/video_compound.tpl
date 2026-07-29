{* Compound Video Viewer - Multiple video objects with single player *}

{* Video Player *}
<div class="archive-compound-player-wrapper">
	<video width="100%" controls controlslist="nodownload" id="compound-video-player" crossorigin="anonymous" class="archive-video-player"  oncontextmenu="return false;">
		{* controlslist="nodownload" only works on Chromium browsers; Firefox and Safari don't support it.*}
		{* Disabling the right click context menu is the only way to prevent the "Save Video as" option in those browsers. *}

		{* Source and tracks will be dynamically loaded via JavaScript *}
	</video>
</div>

{* Grid of Video Items *}
<div class="archive-compound-grid">
    {foreach from=$children item=child name=videoLoop}
        <div class="video-item archive-compound-item"
             data-video-url="{$child.videoUrl}"
             data-video-mime="{$child.videoMime}"
             data-video-title="{$child.title|escape}"
             data-poster-url="{$child.posterUrl}"
             data-captions='{if $child.captions}{$child.captions|@json_encode}{else}[]{/if}'
             data-index="{$smarty.foreach.videoLoop.index}">

            {* Thumbnail/Poster *}
            {if $child.posterUrl}
                <div class="archive-video-item-thumbnail">
                    <img src="{$child.posterUrl}"
                         alt="{$child.title|escape}"
                         class="archive-video-item-thumbnail-image">
                    {* Play icon overlay *}
                    <div class="archive-video-item-play-icon">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="rgba(255,255,255,0.9)">
                            <circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)"/>
                            <path d="M8 5v14l11-7z" fill="rgba(255,255,255,0.9)"/>
                        </svg>
                    </div>
                </div>
            {else}
                <div class="archive-video-item-thumbnail-placeholder">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="#666">
                        <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                    </svg>
                </div>
            {/if}

            {* Title *}
            <h4 class="archive-compound-item-title">
                {$child.title}
            </h4>

            {* Active indicator *}
            <div class="active-indicator">
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
