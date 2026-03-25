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
require_once ROOT_DIR . '/sys/Islandora2/GeographicLocationTaxonomy.php';

use Islandora2\GeographicLocationTaxonomy;

/**
 * Display controller for Islandora 2 Geographic Location taxonomy terms.
 *
 * URL: /Archive2/Place?tid={tid}
 */
class Place extends TaxonomyObject
{
    public function launch()
    {
        global $interface;

        if (!($this->taxonomyObject instanceof GeographicLocationTaxonomy)) {
            $this->logger->error('Place controller received wrong taxonomy type.', [
                'tid'      => $_GET['tid'] ?? null,
                'received' => $this->taxonomyObject ? get_class($this->taxonomyObject) : 'null',
            ]);
            return;
        }

        $place = $this->taxonomyObject;

        $interface->assign('alternate_name',   $place->getAlternateName());
        $interface->assign('broader_location', $place->getBroaderLocation());
        $interface->assign('start_date',       $place->getStartDate());
        $interface->assign('end_date',         $place->getEndDate());
        $interface->assign('notes',            $place->getNotes());
        $interface->assign('geolocation',      $place->getGeolocation());
        $interface->assign('related_place',    $place->getRelatedPlace());
        $interface->assign('address',          $place->getAddress());

        parent::launch();

        $interface->assign('taxonomy_type_template', 'taxonomy_geographic_location');

        $title = $this->taxonomyObject->getTitle();
        parent::display('taxonomy_geographic_location.tpl', $title);
    }
}
