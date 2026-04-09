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
require_once ROOT_DIR . '/sys/Islandora2/CorporateBodyTaxonomy.php';

use Islandora2\CorporateBodyTaxonomy;

/**
 * Display controller for Islandora 2 Corporate Body taxonomy terms.
 *
 * URL: /Archive2/Organization?tid={tid}
 */
class Organization extends TaxonomyObject
{
    public function launch()
    {
        global $interface;

        if (!($this->taxonomyObject instanceof CorporateBodyTaxonomy)) {
            $this->logger->error('Organization controller received wrong taxonomy type.', [
                'tid'      => $_GET['tid'] ?? null,
                'received' => $this->taxonomyObject ? get_class($this->taxonomyObject) : 'null',
            ]);
            return;
        }

        parent::launch();

        $org = $this->taxonomyObject;

        $interface->assign('alternate_name',       $org->getAlternateName());
        $interface->assign('founded_year',         $org->getFoundedYear());
        $interface->assign('dissolved_year',       $org->getDissolvedYear());
        $interface->assign('notes',                $org->getNotes());
        $interface->assign('organization_type',    $org->getOrganizationType());
        $interface->assign('organization_url',     $org->getOrganizationUrl());
        $interface->assign('related_place',        $org->getRelatedPlace());
        $interface->assign('related_organization', $org->getRelatedOrganization());

        $interface->assign('taxonomy_type_template', 'taxonomy_corporate_body');

        $title = $this->taxonomyObject->getTitle();
        parent::display('taxonomy_corporate_body.tpl', $title);
    }
}
