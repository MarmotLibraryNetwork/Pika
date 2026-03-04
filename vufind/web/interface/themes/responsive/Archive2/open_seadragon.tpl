{if $service_file_url}
<script src="https://cdn.jsdelivr.net/npm/openseadragon@5.0/build/openseadragon/openseadragon.min.js"></script>
<div id="openseadragon-viewer"></div>
<script>
{literal}
var viewer = OpenSeadragon({
  id: "openseadragon-viewer",
  prefixUrl: "https://cdn.jsdelivr.net/gh/Benomrans/openseadragon-icons@main/images/",
  tileSources: ["{/literal}{$service_file_url}{literal}"],
  crossOriginPolicy: 'Anonymous',
});
{/literal}
</script>
{/if}
