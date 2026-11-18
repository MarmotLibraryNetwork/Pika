{if $service_file_url}

  <script src="https://cdn.jsdelivr.net/npm/openseadragon@5.0/build/openseadragon/openseadragon.min.js"></script>
 
  {literal}
  <style>
    .osd-multi-wrap {
      display: grid;
      grid-template-rows: 1fr auto;
      gap: 1px;
    }
    #openseadragon-viewer {
      height: 600px;
      background-color: #fff;
      border: 1px solid #c5c5c5;
      border-radius: 6px 6px 0 0;
    }
    #openseadragon-strip-row {
      height: 130px;
      display: flex;
      align-items: stretch;
      /* This row is a grid item; without this, its automatic minimum size is the
         reference strip's full content width, which forces the whole grid column
         (viewer included) thousands of pixels wide. */
      min-width: 0;
      background-color: #fff;
      border: 1px solid #c5c5c5;
      border-radius: 0 0 6px 6px;
    }
    #openseadragon-strip {
      flex: 1 1 auto;
      min-width: 0;
      display: flex;
      /* OpenSeadragon gives .referencestrip an explicit width of panel-width times
         image count and scrolls it by shifting margin-left, so this container must
         clip it or a long strip stretches the whole page. Center the strip only
         when it fits ("safe" falls back to start alignment on overflow; the
         flex-start line covers browsers without safe-alignment support). */
      overflow: hidden;
      justify-content: flex-start;
      justify-content: safe center;
    }
    .osd-strip-arrow {
      /* Shown only when the strip overflows; see the has-overflow toggle below. */
      display: none;
      flex: 0 0 34px;
      align-items: center;
      justify-content: center;
      padding: 0;
      border: none;
      background: transparent;
      color: #555;
      font-size: 18px;
      cursor: pointer;
    }
    #openseadragon-strip-row.has-overflow .osd-strip-arrow {
      display: flex;
    }
    .osd-strip-arrow:hover,
    .osd-strip-arrow:focus {
      background-color: #eee;
      color: #000;
    }
    #osd-strip-prev {
      border-right: 1px solid #c5c5c5;
      border-radius: 0 0 0 6px;
    }
    #osd-strip-next {
      border-left: 1px solid #c5c5c5;
      border-radius: 0 0 6px 0;
    }
    #openseadragon-strip .referencestrip {
      background: transparent !important;
      /* .referencestrip is a flex item here, and flex items shrink by default.
         OpenSeadragon scrolls the strip by making margin-left more negative,
         which shrinks the outer size the flex algorithm needs to fill, so
         without flex-shrink:0 the browser grows the strip's own width to
         compensate every time margin-left changes. That makes the strip's
         width unstable and wraps thumbnails onto hidden extra rows instead of
         keeping them in the single clipped, scrollable row this layout
         requires. Pin it to its OSD-assigned width instead. */
      flex-shrink: 0;
    }
    #openseadragon-strip .referencestrip:focus,
    #openseadragon-strip .referencestrip:focus-visible {
      outline: none;
      border: none;
      box-shadow: none;
    }
    #openseadragon-strip .referencestrip > div {
      float: none !important;
      display: inline-block !important;
      vertical-align: middle;
      /* OpenSeadragon sizes each panel as width + 2px padding and assumes the
         browser's default content-box model, so its click handler divides by
         (panelWidth + 4) to figure out which thumbnail was clicked. Our global
         Bootstrap reset forces border-box on every element, which pulls that
         padding back inside the declared width and shrinks each panel's real
         on-screen pitch by 4px. Left uncorrected, that 4px-per-panel gap
         accumulates across the strip, so clicks land increasingly right of
         the intended thumbnail on strips with many images. Restoring
         content-box here keeps the real geometry matching what OpenSeadragon's
         click math expects. */
      box-sizing: content-box;
      /* ReferenceStrip.setFocus() paints the selected panel #999 and every
         previously selected panel #000 with inline styles, which leaves black
         boxes behind as you page through. Neutralize those inline backgrounds
         and mark the current panel with a class instead (see markCurrentPanel). */
      background: transparent !important;
    }
    #openseadragon-strip .referencestrip > div.osd-strip-current {
      background: #999 !important;
    }
</style>
{/literal}
  <div class="osd-multi-wrap">
    <div id="openseadragon-viewer"></div>
    <div id="openseadragon-strip-row">
      <button type="button" id="osd-strip-prev" class="osd-strip-arrow" aria-label="Scroll thumbnails backward"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
      <div id="openseadragon-strip"></div>
      <button type="button" id="osd-strip-next" class="osd-strip-arrow" aria-label="Scroll thumbnails forward"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
    </div>
  </div>

  <script>
    {literal}
      var tileSources = [{/literal}
        {foreach from=$service_file_url item=url}"{$url}",
        {/foreach}];
        {literal}

          var stripContainer = document.getElementById('openseadragon-strip');
          var viewer = OpenSeadragon({
            id: "openseadragon-viewer",
            preserveViewport: true,
            visibilityRatio: 1,
            sequenceMode: true,
            showReferenceStrip: true,
            autoHideControls: false,
            tileSources: tileSources,
            crossOriginPolicy: 'Anonymous',
            loadTilesWithAjax: true,
          });

          var stripRow = document.getElementById('openseadragon-strip-row');

          // Highlight the current page's thumbnail. OpenSeadragon tracks it as
          // referenceStrip.currentSelected (updated in setFocus before 'page' fires).
          function markCurrentPanel() {
            var rs = viewer.referenceStrip;
            if (!rs || !rs.element) {
              return;
            }
            var panels = rs.element.children;
            for (var i = 0; i < panels.length; i++) {
              panels[i].classList.toggle('osd-strip-current', panels[i] === rs.currentSelected);
            }
          }

          // OpenSeadragon's reference strip maps the arrow keys to scrolling the
          // strip, so a focused strip never changes the image. Replace its keydown
          // handler so left/up go to the previous image and right/down to the next;
          // setFocus() then scrolls the strip to keep the current thumbnail visible.
          function patchStripKeys() {
            var rs = viewer.referenceStrip;
            // referenceStrip.tracker isn't in the official docs
            // rs.tracker is a MouseTracker object
            if (!rs || !rs.tracker || rs.pikaKeysPatched) {
              return;
            }
            // console.log(rs.tracker);
            rs.tracker.keyDownHandler = function (event) {
              var delta = 0;
              if (!event.ctrl && !event.alt && !event.meta) {
                switch (event.keyCode) {
                  case 37: // left
                  case 38: // up
                    delta = -1;
                    break;
                  case 39: // right
                  case 40: // down
                    delta = 1;
                    break;
                }
              }
              if (!delta) {
                event.preventDefault = false;
                return;
              }
              var target = viewer.currentPage() + delta;
              if (target >= 0 && target < tileSources.length) {
                viewer.goToPage(target);
              }
              event.preventDefault = true;
            };
            rs.pikaKeysPatched = true;
          }

          viewer.addHandler('page', markCurrentPanel);

          viewer.addHandler('open', function () {
            patchStripKeys();
            markCurrentPanel();
            var strip = viewer.referenceStrip && viewer.referenceStrip.element;
            if (strip && stripContainer && strip.parentNode !== stripContainer) {
              stripContainer.appendChild(strip);
            }
            if (strip && stripRow) {
              var stripWidth = parseFloat(strip.style.width) || 0;
              stripRow.classList.toggle('has-overflow', stripWidth > stripContainer.clientWidth + 1);
            }
          });

          // Advance the strip by dispatching wheel events at it: OpenSeadragon's own
          // scroll handler then does the 60px-per-tick movement, bounds checking, and
          // lazy thumbnail loading. deltaY < 0 scrolls forward (later thumbnails).
          function scrollStrip(deltaY) {
            var strip = viewer.referenceStrip && viewer.referenceStrip.element;
            if (!strip) {
              return;
            }
            // Enough ticks to move about 80% of the visible strip, one per frame so
            // it slides instead of teleporting.
            var ticks = Math.max(2, Math.round((stripContainer.clientWidth * 0.8) / 60));
            (function step() {
              strip.dispatchEvent(new WheelEvent('wheel', { deltaY: deltaY, cancelable: true }));
              if (--ticks > 0) {
                window.requestAnimationFrame(step);
              }
            })();
          }
          document.getElementById('osd-strip-prev').addEventListener('click', function () { scrollStrip(1); });
          document.getElementById('osd-strip-next').addEventListener('click', function () { scrollStrip(-1); });
        {/literal}
      </script>
    {/if}
<br class="clear" />
