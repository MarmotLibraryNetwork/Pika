<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * SearchObjectFactory Class
 *
 * This is a factory class to build objects for managing searches.
 *
 * @author      Demian Katz <demian.katz@villanova.edu>
 * @access      public
 */
class SearchObjectFactory {

	/**
	 * initSearchObject
	 *
	 * This constructs a search object for the specified engine.
	 *
	 * @access  public
	 * @param string $engine     The type of SearchObject to build (Solr/Summon).
	 * @return  mixed               The search object on success, false otherwise
	 */
	static function initSearchObject(string $engine = 'Solr'): mixed{
		$path =  ROOT_DIR . "/sys/SearchObject/$engine.php";
		// Options: Solr, Genealogy, Islandora, Islandora2, UserListSolr, UserListIslandora, UserListIslandora2
		if (is_readable($path)){
			require_once $path;
			$class = 'SearchObject_' . $engine;
			if (class_exists($class)){
				/** @var SearchObject_UserListIslandora|SearchObject_UserListIslandora2|SearchObject_UserListSolr|SearchObject_Base|SearchObject_Solr|SearchObject_Genealogy|SearchObject_Islandora|SearchObject_Islandora2 $searchObject */
				$searchObject = new $class();
				return $searchObject;
			}
		}

		global $pikaLogger;
		$pikaLogger->withName(__CLASS__)->error("Failed to initialize SearchObject class for engine $engine");
		return false;
	}

	/**
	 * deminifySerialized
	 *
	 * Safely reconstitute a search object from the serialized minSO data stored in
	 * SearchEntry::$search_object. Restricts unserialize() to the minSO class to
	 * prevent PHP object injection, and fails cleanly (logging and returning false)
	 * if the stored data is missing, corrupt, or not a valid minSO instance.
	 *
	 * @access  public
	 * @param   string|null  $serializedMinSO   Serialized minSO data from the database.
	 * @return  mixed                           The search object on success, false otherwise
	 */
	static function deminifySerialized($serializedMinSO){
		global $pikaLogger;
		$logger = $pikaLogger->withName(__CLASS__);

		if (empty($serializedMinSO)){
			$logger->error('Cannot load saved search: no serialized search data was stored');
			return false;
		}

		try {
			$minSO = unserialize($serializedMinSO, ['allowed_classes' => ['minSO']]);
		} catch (\Throwable $e){
			$logger->error('Failed to unserialize saved search: ' . $e->getMessage());
			return false;
		}

		if (!($minSO instanceof minSO)){
			$logger->error('Failed to load saved search: stored data is not a valid minSO object');
			return false;
		}

		try {
			return self::deminify($minSO);
		} catch (\Throwable $e){
			$logger->error('Failed to deminify saved search: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * deminify
	 *
	 * Construct an appropriate Search Object from a MinSO object.
	 *
	 * @access  public
	 * @param   minSO  $minSO      The MinSO object to use as the base.
	 * @return  mixed               The search object on success, false otherwise
	 */
	static function deminify($minSO){
		// To avoid excessive constructor calls, we'll keep a static cache of
		// objects to use for the deminification process:
		/** @var SearchObject_Base[] $objectCache */
		static $objectCache = [];

		// Figure out the engine type for the object we're about to construct:
		switch ($minSO->ty){
			case 'islandora2' :
				$type = 'Islandora2';
				break;
			case 'islandora' :
				$type = 'Islandora';
				break;
			case 'genealogy' :
				$type = 'Genealogy';
				break;
			default:
				// When Solr, ty can be either 'basic' or 'advanced'
				$type = 'Solr';
				break;
		}

		// Construct a new object if we don't already have one:
		if (!isset($objectCache[$type])){
			$objectCache[$type] = self::initSearchObject($type);
		}

		// Populate and return the deminified object:
		$objectCache[$type]->deminify($minSO);
		//MDN 1/5/2015 return a clone of the search object since we may deminify several search objects in a single page load. 
		return clone $objectCache[$type];
	}
}
