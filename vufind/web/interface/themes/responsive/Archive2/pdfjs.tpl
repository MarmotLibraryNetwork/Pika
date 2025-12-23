{* 
<canvas id="c"></canvas>

<script type="module">
    import * as pdfjsLib from "https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.mjs";
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/pdf.worker.mjs";

    const pdf = await pdfjsLib.getDocument("{$pdf_url}").promise;
    const page = await pdf.getPage(1);
    const viewport = page.getViewport({ scale: 1.5 });

    const canvas = document.getElementById("c");
    canvas.width = viewport.width;
    canvas.height = viewport.height;

    await page.render({ canvasContext: canvas.getContext("2d"), viewport }).promise;
</script> *}

<iframe class="pdf" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen="" frameborder="no" width="100%"
    height="1000px"
    src="https://islandoratest.marmot.org/libraries/pdf.js/web/viewer.html?file={$pdf_url}"
    data-src="https://islandoratest.marmot.org/system/files/2025-05/islandora_30362.pdf"
title="islandora_30362.pdf"></iframe>