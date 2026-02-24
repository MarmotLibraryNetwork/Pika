{* Helper template that extracts data from mediaObject and includes the PDF viewer *}
{php}
    global $interface;
    global $configArray;

    // Get the mediaObject from the template variable
    $childMediaObject = $this->get_template_vars('childMediaObject');

    // Extract PDF data
    $pdf = $childMediaObject->getOriginalMedia();
    $interface->assign('pdf_url', $pdf->fileUrl);

    // Build iframe source
    $iframeSrc = $configArray['Islandora2']['url'] ?? '';
    $iframeSrc = rtrim($iframeSrc, '/') . "/libraries/pdf.js/web/viewer.html?file=" . urlencode($pdf->fileUrl);
    $interface->assign('iframe_src', $iframeSrc);

    // Assign title
    $interface->assign('title', $childMediaObject->getTitle());
{/php}

{* Now include the actual viewer template *}
{include file="Archive2/pdfjs.tpl"}
