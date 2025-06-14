<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
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
 * Template database object that stores options for what kind of records to
 * include or exclude from Hoopla in their search results. (The library scope)
 *
 * @category Pika
 * @author   : Pascal Brammeier
 * Date: 4/29/2019
 *
 */

require_once 'DB/DataObject.php';

abstract class HooplaSetting extends DB_DataObject {

	public $id;
	public $kind;                      // Hoopla's version of format
	public $maxPrice;                  // Exclude Titles with a value for price in the Hoopla Extract table greater than this
	public $excludeParentalAdvisory;   // Titles with `pa` = 1 in Hoopla Extract table
	public $excludeProfanity;          // Titles with profanity = 1 in Hoopla Extract table
	public $includeChildrenTitlesOnly; // Titles with children = 1 in Hoopla Extract table


	static function getObjectStructure(){
		$hooplaKinds = self::getHooplaKinds();
		$structure   = [
			'id'                        => ['property' => 'id',                        'type' => 'label',    'label' => 'Id', 'description' => 'The unique id of this association'],
			'kind'                      => ['property' => 'kind',                      'type' => 'enum',     'label' => 'Hoopla Format', 'values' => $hooplaKinds, 'description' => 'The Hoopla format these settings will apply to.', 'required' => true],
			'maxPrice'                  => ['property' => 'maxPrice',                  'type' => 'number',   'label' => 'Hoopla Max. Price', 'description' => 'The maximum price per use to include in search results. (0 = include everything; 0.01 to exclude the entire format)', 'min' => 0, 'step' => "0.01", 'default' => '0.00' /* leave default as a string so that value is displayed in form*/],
			'excludeParentalAdvisory'   => ['property' => 'excludeParentalAdvisory',   'type' => 'checkbox', 'label' => 'Exclude Parental Advisory Titles', 'description' => 'Whether or not titles Hoopla has marked as having a parental advisory notice are excluded.', 'default' => 0],
			'excludeProfanity'          => ['property' => 'excludeProfanity',          'type' => 'checkbox', 'label' => 'Exclude Titles with Profanity', 'description' => 'Whether or not titles Hoopla has marked as having profanity are excluded.', 'default' => 0],
			'includeChildrenTitlesOnly' => ['property' => 'includeChildrenTitlesOnly', 'type' => 'checkbox', 'label' => 'Include Titles for Children only', 'description' => 'Whether or not only titles Hoopla has marked as for children are included.', 'default' => 0],

		];
		return $structure;
	}

	static function getHooplaKinds(){
		/** @var Memcache $memCache */
		global $memCache;
		global $instanceName;
		$memCacheKey = "hoopla_kinds_array_$instanceName";
		$kinds       = $memCache->get($memCacheKey);
		if (!$kinds){
			require_once ROOT_DIR . '/sys/Hoopla/HooplaExtract.php';
			$hooplaExtractObj = new HooplaExtract();
			$hooplaExtractObj->groupBy('kind');
			$kinds = $hooplaExtractObj->fetchAll('kind');
			if (!empty($kinds)){
				$kinds = array_combine($kinds, $kinds);
				global $configArray;
				$memCache->set($memCacheKey, $kinds, 0, $configArray['Caching']['hoopla_kinds_array']);
			}
		}
		return $kinds;
	}

}
