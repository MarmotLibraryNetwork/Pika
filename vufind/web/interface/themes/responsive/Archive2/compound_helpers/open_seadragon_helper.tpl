{* Helper template that extracts data from mediaObject and includes the open_seadragon viewer *}
{php}
    global $interface;
    global $configArray;

    // Get the mediaObject from the template variable
    $childMediaObject = $this->get_template_vars('childMediaObject');

    // Extract service file URL
    $serviceFile = $childMediaObject->getServiceFile();
    $serviceFileUrl = null;

    if ($serviceFile && isset($serviceFile->fileUrl)) {
        $baseUrl = $configArray['Islandora2']['url'] ?? '';
        $baseUrl = rtrim($baseUrl, '/');
        $serviceFileUrl = $baseUrl . "/cantaloupe/iiif/2/" . urlencode($serviceFile->fileUrl);
    }

    // Assign to interface for the included template
    $interface->assign('service_file_url', $serviceFileUrl);
{/php}

{* Now include the actual viewer template *}
{include file="Archive2/open_seadragon.tpl"}
