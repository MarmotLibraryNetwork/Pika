{if $service_file_url}
<script src="https://cdn.jsdelivr.net/npm/openseadragon@5.0/build/openseadragon/openseadragon.min.js"></script>
{literal}
<style>
  #openseadragon-viewer {
    height: 600px;
    background-color: #fff;
    border: 1px solid #c5c5c5;
    border-radius: 6px;
  }
</style>
{/literal}
<div id="openseadragon-viewer"></div>
<script>
{literal}
var viewer = OpenSeadragon({
  id: "openseadragon-viewer",
  //prefixUrl: "https://cdn.jsdelivr.net/gh/Benomrans/openseadragon-icons@main/images/",
  tileSources: ["{/literal}{$service_file_url}{literal}"],
  visibilityRatio: 1,
  minZoomLevel: 1,
  defaultZoomLevel: 1,
  autoHideControls: false,
  crossOriginPolicy: 'Anonymous',
  loadTilesWithAjax: true,
});
{/literal}
</script>
{/if}
