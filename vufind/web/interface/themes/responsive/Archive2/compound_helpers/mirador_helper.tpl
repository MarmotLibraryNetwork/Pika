{* Helper template that extracts data from mediaObject and includes the mirador viewer *}
{php}
    global $interface;

    // Get the mediaObject from the template variable
    $childMediaObject = $this->get_template_vars('childMediaObject');

    // Mirador needs the node ID
    $interface->assign('nid', $childMediaObject->getNodeId());
{/php}

{* Now include the actual viewer template *}
{include file="Archive2/mirador.tpl"}
