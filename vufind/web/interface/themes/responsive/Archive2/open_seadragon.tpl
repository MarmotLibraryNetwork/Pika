{if $service_file_url}
<script src="https://cdn.jsdelivr.net/npm/openseadragon@5.0/build/openseadragon/openseadragon.min.js"></script>
<div id="openseadragon-viewer"></div>
<script>
{literal}
var viewer = OpenSeadragon({
  id: "openseadragon-viewer",
  prefixUrl: "//cdn.jsdelivr.net/gh/Benomrans/openseadragon-icons@main/images/",
  tileSources: "/Archive/AJAX?method=fetchCantaloupeMaifest&sf={/literal}{$service_file_url}{literal}",
});
{/literal}
</script>
{/if}
