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
    #openseadragon-strip {
      height: 130px;
      display: flex;
      justify-content: center;
      background-color: #fff;
      border: 1px solid #c5c5c5;
      border-radius: 0 0 6px 6px;
    }
    #openseadragon-strip .referencestrip {
      background: transparent !important;
      
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
    }
  </style>
{/literal}
  <div class="osd-multi-wrap">
    <div id="openseadragon-viewer"></div>
    <div id="openseadragon-strip"></div>
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
            //prefixUrl: "https://cdn.jsdelivr.net/gh/Benomrans/openseadragon-icons@main/images/",
            preserveViewport: true,
            visibilityRatio: 1,
            minZoomLevel: 1,
            defaultZoomLevel: 1,
            sequenceMode: true,
            showReferenceStrip: true,
            autoHideControls: false,
            tileSources: tileSources,
            crossOriginPolicy: 'Anonymous',
            loadTilesWithAjax: true,
          });

          viewer.addHandler('open', function () {
            var strip = viewer.referenceStrip && viewer.referenceStrip.element;
            if (strip && stripContainer && strip.parentNode !== stripContainer) {
              stripContainer.appendChild(strip);
            }
          });
        {/literal}
      </script>
    {/if}
<br class="clear" />
