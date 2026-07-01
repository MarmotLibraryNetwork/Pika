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

        if ($this->taxonomyObject === null) {
            parent::launch(); // shows error page and halts
        }

        if (!($this->taxonomyObject instanceof PersonTaxonomy)) {
            $this->logger->error('Person controller received wrong taxonomy type.', [
                'tid'      => $_GET['tid'] ?? null,
                'received' => $this->taxonomyObject ? get_class($this->taxonomyObject) : 'null',
            ]);
            return;
        }

        parent::launch();

        $person = $this->taxonomyObject;

        // Identity fields
        $interface->assign('family_name',         $person->getFamilyName());
        $interface->assign('given_name',          $person->getGivenName());
        $interface->assign('middle_name',         $person->getMiddleName());
        $interface->assign('maiden_name',         $person->getMaidenName());
        $interface->assign('alternate_name',      $person->getAlternateName());
        $interface->assign('birth_year',          $person->getBirthYear());
        $interface->assign('death_year',          $person->getDeathYear());
        $interface->assign('notes',               $person->getNotes());
        $interface->assign('race_ethnicity',      $person->getRaceEthnicity());

        // Military data — only assign the block if any field is populated
        if ($person->hasMilitaryData()) {
            $interface->assign('military', [
                'branch'     => $person->getMilitaryBranch(),
                'branch_url' => $person->getMilitaryBranchUrl(),
                'conflict'     => $person->getMilitaryConflict(),
                'conflict_url' => $person->getMilitaryConflictUrl(),
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
                'position_start' => $person->getAcademicPositionStartDate(),
                'position_end'   => $person->getAcademicPositionEndDate(),
                'degree_name'    => $person->getDegreeName(),
                'discipline'     => $person->getDegreeDiscipline(),
                'graduation_date' => $person->getGraduationDate(),
            ]);
        } else {
            $interface->assign('academic', null);
        }

        $interface->assign('related_place', $person->getRelatedPlace());

        // Genealogy link is Person-specific; prepend it to the external links
        // already normalized and assigned by parent::launch().
        $genealogyLink = $person->getGenealogyLink();
        if ($genealogyLink) {
            $existingLinks = $interface->getVariable('links') ?? [];
            $interface->assign('links', array_merge([$genealogyLink], $existingLinks) ?: null);
        }

        // Genealogy database data, linked via field_genealogy_link
        $genealogyPerson = $this->loadGenealogyPerson($person);
        $interface->assign('obituaries', $this->loadObituaries($genealogyPerson));
        $interface->assign('burial',     $this->loadBurialData($genealogyPerson));

        $interface->assign('taxonomy_type_template', 'taxonomy_person');

        $title = $this->taxonomyObject->getTitle();
        parent::display('taxonomy_person.tpl', $title);
    }

    /**
     * Load the Genealogy Person record linked via field_genealogy_link, or null when absent.
     *
     * @param PersonTaxonomy $person
     * @return \Person|null
     */
    private function loadGenealogyPerson(PersonTaxonomy $person): ?\Person
    {
        $personId = $person->getGenealogyPersonId();
        if (!$personId) {
            return null;
        }

        require_once ROOT_DIR . '/sys/Genealogy/Person.php';
        $genealogyPerson           = new \Person();
        $genealogyPerson->personId = $personId;
        return $genealogyPerson->find(true) ? $genealogyPerson : null;
    }

    /**
     * @param \Person|null $genealogyPerson
     * @return \Obituary[]|null
     */
    private function loadObituaries(?\Person $genealogyPerson): ?array
    {
        if (!$genealogyPerson) {
            return null;
        }
        $obituaries = $genealogyPerson->obituaries;
        return !empty($obituaries) ? $obituaries : null;
    }

    /**
     * Return burial details from the Genealogy database as an array, or null when
     * no burial fields are populated.
     *
     * @param \Person|null $genealogyPerson
     * @return array|null
     */
    private function loadBurialData(?\Person $genealogyPerson): ?array
    {
        if (!$genealogyPerson) {
            return null;
        }

        $burial = [
            'cemetery_name'         => $genealogyPerson->cemeteryName         ?: null,
            'cemetery_location'     => $genealogyPerson->cemeteryLocation     ?: null,
            'cemetery_avenue'       => $genealogyPerson->cemeteryAvenue       ?: null,
            'addition'              => $genealogyPerson->addition              ?: null,
            'block'                 => $genealogyPerson->block                 ?: null,
            'lot'                   => $genealogyPerson->lot                   ?: null,
            'grave'                 => $genealogyPerson->grave                 ?: null,
            'tombstone_inscription' => $genealogyPerson->tombstoneInscription ?: null,
            'mortuary_name'         => $genealogyPerson->mortuaryName         ?: null,
        ];

        $hasData = array_filter($burial, fn($v) => $v !== null);
        return $hasData ? $burial : null;
    }
}
