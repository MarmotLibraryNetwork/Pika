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

require_once ROOT_DIR . '/services/Archive2/TaxonomyObject.php';
require_once ROOT_DIR . '/sys/Islandora2/EventTaxonomy.php';

use Islandora2\EventTaxonomy;

/**
 * Display controller for Islandora 2 Event taxonomy terms.
 *
 * URL: /Archive2/Event?tid={tid}
 */
class Event extends TaxonomyObject
{
    public function launch()
    {
        global $interface;

        if (!($this->taxonomyObject instanceof EventTaxonomy)) {
            $this->logger->error('Event controller received wrong taxonomy type.', [
                'tid'      => $_GET['tid'] ?? null,
                'received' => $this->taxonomyObject ? get_class($this->taxonomyObject) : 'null',
            ]);
            return;
        }

        parent::launch();

        $event = $this->taxonomyObject;

        $interface->assign('alternate_name',      $event->getAlternateName());
        $interface->assign('start_date',          $event->getStartDate());
        $interface->assign('end_date',            $event->getEndDate());
        $interface->assign('notes',               $event->getNotes());
        $interface->assign('event_city',          $event->getEventCity());
        $interface->assign('event_county',        $event->getEventCounty());
        $interface->assign('event_state',         $event->getEventState());
        $interface->assign('related_place',       $event->getRelatedPlace());
        $interface->assign('related_organization',$event->getRelatedOrganization());

        

        $interface->assign('taxonomy_type_template', 'taxonomy_event');

        $title = $this->taxonomyObject->getTitle();
        parent::display('taxonomy_event.tpl', $title);
    }
}
