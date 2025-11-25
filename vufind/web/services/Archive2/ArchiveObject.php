<?php

/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Archive2;

require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';

use Islandora2\I2ObjectFactory;

/* responsible for displaying template */
class ArchiveObject extends \Action
{
    protected $mediaObject;
    /** */
    protected int $nid;
    
    
    public function __construct() {
        $nid = (int)$_GET['nid'];
        if($nid <= 0) {
            // redirect to 404;
        }
        $factory = new I2ObjectFactory();
        $this->mediaObject = $factory->fromNodeId($nid);
    }

    public function launch()
    {
        global $interface;
        
        $interface->assign('showExploreMore', true);

        // Viewing permissions
        $can_view = false;
        $pika_usage = strtolower($this->mediaObject->pika_usage);
        if($pika_usage === 'yes') {
            $can_view = true;
        }
        $interface->assign('canView', $can_view);

        // Language
        $language = '';
        if($this->mediaObject->language['name'] && $this->mediaObject->language['name'] != '') {
            $language = $this->mediaObject->language['name'];
        }
        $interface->assign('language', $language);
        
        // Title
        $title = $this->mediaObject->getTitle();
        $interface->assign('title', $title);

        // Description
        $description = ($this->mediaObject->getDescription() !== null) ? $this->mediaObject->getDescription() : '' ;
        $interface->assign('description', $description);

        $extent = ($this->mediaObject->extent !== null) ? $this->mediaObject->extent : '';
        $interface->assign('physical_description', $extent);
        
    }

    public function display($mainContentTemplate, $pageTitle = null, $sidebarTemplate = 'Search/home-sidebar.tpl') 
    {
        parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate);
    }
}