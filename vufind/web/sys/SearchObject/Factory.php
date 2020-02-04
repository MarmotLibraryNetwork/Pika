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
	 * @param   string  $engine     The type of SearchObject to build (Solr/Summon).
	 * @return  mixed               The search object on success, false otherwise
	 */
	static function initSearchObject($engine = 'Solr')
	{
		global $configArray;

		$path = "{$configArray['Site']['local']}/sys/SearchObject/{$engine}.php";
		if (is_readable($path)) {
			require_once $path;
			$class = 'SearchObject_' . $engine;
			if (class_exists($class)) {
				/** @var Solr|SearchObject_Base $searchObject */
				$searchObject = new $class();
				return $searchObject;
			}
		}

		return false;
	}

	/**
	 * deminify
	 *
	 * Construct an appropriate Search Object from a MinSO object.
	 *
	 * @access  public
	 * @param   object  $minSO      The MinSO object to use as the base.
	 * @return  mixed               The search object on success, false otherwise
	 */
	static function deminify($minSO)
	{
		// To avoid excessive constructor calls, we'll keep a static cache of
		// objects to use for the deminification process:
		/** @var SearchObject_Base[] $objectCache */
		static $objectCache = array();

		// Figure out the engine type for the object we're about to construct:
		switch($minSO->ty) {
			case 'WorldCat':
			case 'WorldCatAdvanced':
				$type = 'WorldCat';
				break;
			case 'islandora' :
				$type = 'Islandora';
				break;
			case 'genealogy' :
				$type = 'Genealogy';
				break;
			default:
				$type = 'Solr';
				break;
		}

		// Construct a new object if we don't already have one:
		if (!isset($objectCache[$type])) {
			$objectCache[$type] = self::initSearchObject($type);
		}

		// Populate and return the deminified object:
		$objectCache[$type]->deminify($minSO);
		//MDN 1/5/2015 return a clone of the search object since we may deminify several search objects in a single page load. 
		return clone $objectCache[$type];
	}
}
