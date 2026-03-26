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
require_once ROOT_DIR . '/sys/Islandora2/PersonTaxonomy.php';

use Islandora2\PersonTaxonomy;

/**
 * Display controller for Islandora 2 Person taxonomy terms.
 *
 * URL: /Archive2/Person?tid={tid}
 */
class Person extends TaxonomyObject
{
    public function launch()
    {
        global $interface;

        if (!($this->taxonomyObject instanceof PersonTaxonomy)) {
            $this->logger->error('Person controller received wrong taxonomy type.', [
                'tid'      => $_GET['tid'] ?? null,
                'received' => $this->taxonomyObject ? get_class($this->taxonomyObject) : 'null',
            ]);
            return;
        }

        $person = $this->taxonomyObject;

        // Identity fields
        $interface->assign('family_name',    $person->getFamilyName());
        $interface->assign('given_name',     $person->getGivenName());
        $interface->assign('middle_name',    $person->getMiddleName());
        $interface->assign('maiden_name',    $person->getMaidenName());
        $interface->assign('alternate_name', $person->getAlternateName());
        $interface->assign('birth_year',     $person->getBirthYear());
        $interface->assign('death_year',     $person->getDeathYear());
        $interface->assign('notes',          $person->getNotes());
        $interface->assign('race_ethnicity', $person->getRaceEthnicity());

        // Military data — only assign the block if any field is populated
        if ($person->hasMilitaryData()) {
            $interface->assign('military', [
                'branch'    => $person->getMilitaryBranch(),
                'conflict'  => $person->getMilitaryConflict(),
                'rank'      => $person->getMilitaryRank(),
                'is_pow'    => $person->getMilitaryIsPow(),
                'svc_start' => $person->getMilitarySvcStartDate(),
                'svc_end'   => $person->getMilitarySvcEndDate(),
            ]);
        } else {
            $interface->assign('military', null);
        }

        // Academic data — only assign the block if any field is populated
        if ($person->hasAcademicData()) {
            $interface->assign('academic', [
                'position_title' => $person->getAcademicPositionTitle(),
                'degree_name'    => $person->getDegreeName(),
                'discipline'     => $person->getDegreeDiscipline(),
                'graduation_date' => $person->getGraduationDate(),
            ]);
        } else {
            $interface->assign('academic', null);
        }

        $interface->assign('related_place', $person->getRelatedPlace());

        parent::launch();

        $interface->assign('taxonomy_type_template', 'taxonomy_person');

        $title = $this->taxonomyObject->getTitle();
        parent::display('taxonomy_person.tpl', $title);
    }
}
