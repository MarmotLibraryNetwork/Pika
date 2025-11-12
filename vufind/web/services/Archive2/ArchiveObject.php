<?php

namespace Archive2;


/* responsible for displaying template */
class ArchiveObject extends \Action
{

    public function launch()
    {
        // TODO: Implement launch() method.
    }

    public function display($mainContentTemplate, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl') 
    {
        parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
    }
}