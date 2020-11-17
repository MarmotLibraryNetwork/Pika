<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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

/**
 * Indexing information for what records should be included in a particular scope
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/18/2015
 * Time: 10:31 AM
 */

require_once 'DB/DataObject.php';

class RecordToInclude extends DB_DataObject {
	public $id;
	public $indexingProfileId;
	public $location;
	public $subLocation;
	public $iType;
	public $audience;
	public $format;
	public $includeHoldableOnly;
	public $includeItemsOnOrder;
	public $includeEContent;
	//The next 3 fields allow inclusion or exclusion of records based on a marc tag
	public $marcTagToMatch;
	public $marcValueToMatch;
	public $includeOnlyMatches;
	//The next 2 fields determine how urls are constructed
	public $urlToMatch;
	public $urlReplacement;

	public $weight;

	static function getObjectStructure(){
		require_once ROOT_DIR . '/sys/Indexing/IndexingProfile.php';
		$indexingProfiles = IndexingProfile::getAllIndexingProfileNames();
		$structure = [
			'id'                    => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of this association'],
			'weight'                => ['property' => 'weight', 'type' => 'integer', 'label' => 'Weight', 'description' => 'The sort order of rule', 'default' => 0],
			'indexingProfileId'     => ['property' => 'indexingProfileId', 'type' => 'enum', 'values' => $indexingProfiles, 'label' => 'Indexing Profile Id', 'description' => 'The Indexing Profile this map is associated with'],
			'location'              => ['property' => 'location', 'type' => 'text', 'label' => 'Location', 'description' => 'A regular expression for location codes to include', 'maxLength' => '100', 'default' => '.*', 'required' => true],
			'subLocation'           => ['property' => 'subLocation', 'type' => 'text', 'label' => 'Sub Location', 'description' => 'A regular expression for sublocation codes to include', 'maxLength' => '100', 'required' => false],
			'iType'                 => ['property' => 'iType', 'type' => 'text', 'label' => 'iType', 'description' => 'A regular expression for item types to include', 'maxLength' => '100', 'required' => false],
			'audience'              => ['property' => 'audience', 'type' => 'text', 'label' => 'Audience', 'description' => 'A regular expression for audiences to include', 'maxLength' => '100', 'required' => false],
			'format'                => ['property' => 'format', 'type' => 'text', 'label' => 'Format', 'description' => 'A regular expression for formats to include', 'maxLength' => '100', 'required' => false],
			'includeHoldableOnly'   => ['property' => 'includeHoldableOnly', 'type' => 'checkbox', 'label' => 'Include Holdable Only', 'description' => 'Whether or not non-holdable records are included'],
			'includeItemsOnOrder'   => ['property' => 'includeItemsOnOrder', 'type' => 'checkbox', 'label' => 'Include Items On Order', 'description' => 'Whether or not order records are included'],
			'includeEContent'       => ['property' => 'includeEContent', 'type' => 'checkbox', 'label' => 'Include e-content Items', 'description' => 'Whether or not e-Content should be included'],
			'marcTagToMatch'        => ['property' => 'marcTagToMatch', 'type' => 'text', 'label' => 'Tag To Match', 'description' => 'MARC tag(s) to match', 'maxLength' => '100', 'required' => false],
			'marcValueToMatch'      => ['property' => 'marcValueToMatch', 'type' => 'text', 'label' => 'Value To Match', 'description' => 'The value to match within the MARC tag(s) if multiple tags are specified, a match against any tag will count as a match of everything', 'maxLength' => '100', 'required' => false],
			'includeExcludeMatches' => ['property' => 'includeExcludeMatches', 'type' => 'enum', 'values' => ['1' => 'Include Matches', '0' => 'Exclude Matches'], 'label' => 'Include Matches?', 'description' => 'Whether or not matches are included or excluded', 'default' => 1],
			'urlToMatch'            => ['property' => 'urlToMatch', 'type' => 'text', 'label' => 'URL To Match', 'description' => 'URL to match when rewriting urls', 'maxLength' => '100', 'required' => false],
			'urlReplacement'        => ['property' => 'urlReplacement', 'type' => 'text', 'label' => 'URL Replacement', 'description' => 'The replacement pattern for url rewriting', 'maxLength' => '100', 'required' => false],
		];
		return $structure;
	}
}
