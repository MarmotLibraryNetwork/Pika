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
require_once ROOT_DIR . '/sys/Islandora2/Functions.php';

/**
 * Generic taxonomy term redirect controller.
 *
 * Resolves /Archive2/Term/{tid} to the canonical typed URL
 * (/Archive2/Person, /Archive2/Organization, /Archive2/Place, /Archive2/Event)
 * based on the term's vocabulary. Terms whose vocabulary is not displayed in
 * Pika result in an error page rather than a redirect.
 */
class Term extends TaxonomyObject
{

    public function __construct()
    {
        parent::__construct();

        // getTid() > 0 guards against getTaxonomyAbsoluteUrl() returning baseUrl.'#'
        // when the API response omits the tid field on an otherwise valid term object.
        if ($this->taxonomyObject !== null && $this->taxonomyObject->getTid() > 0) {
            $vocab = strtolower($this->taxonomyObject->getVocabularyMachineName() ?? '');
            if (isset(ISLANDORA2_VOCAB_URL_MAP[$vocab])) {
                $absoluteUrl = getTaxonomyAbsoluteUrl($this->taxonomyObject);
                header("Location: {$absoluteUrl}");
                exit();
            }
        }
    }

    public function launch()
    {
        if ($this->taxonomyObject === null) {
            parent::launch(); // TaxonomyObject::launch() handles null → shows unavailable.tpl and dies
        }

        // Term exists, but its vocabulary is not displayed as a standalone page in Pika
        parent::display('term-not-displayed.tpl', 'Archive Term Not Available');
        //die();
    }

}