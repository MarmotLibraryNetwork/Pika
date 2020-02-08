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
 * Responsible for instantiating Catalog Connections to minimize making multiple concurrent connections
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/26/15
 * Time: 8:38 PM
 */

class CatalogFactory {
	/** @var array An array of connections keyed by driver name */
	private static $catalogConnections = array();

	/**
	 * @param string|null $driver
	 * @param AccountProfile $accountProfile
	 * @return CatalogConnection
	 */
	public static function getCatalogConnectionInstance($driver = null, $accountProfile = null){
		if ($driver == null){
			// When the driver & account profile aren't set, we are dealing with a situation where a user is not logged in
			// but we need a connection to circulation system

			/** @var IndexingProfile $activeRecordIndexingProfile */
			global $activeRecordIndexingProfile;
			if (!empty($activeRecordIndexingProfile->patronDriver)){
				// The is for when we are in a record view and we need additional bibliographic-related information from the circulation system
				// eg. periodical issue summaries, periodical checkin grids, current hold queue size

				$driver = $activeRecordIndexingProfile->patronDriver;

				// Load the account profile based on the indexing profile name.  The AccountProfile is where we determine which
				// external system is associated with a record source
				$accountProfile               = new AccountProfile();
				$accountProfile->recordSource = $activeRecordIndexingProfile->name;
				if (!$accountProfile->find(true)){
					$accountProfile = null;
				}
			}else{
				// This is for situations where we need to connect to a circulation system but can't log in a User
				// eg. Self Registration, Pin Reset, Email Pin

				$accountProfiles = new AccountProfile();
				$accountProfiles = $accountProfiles->fetchAll();
				if (count($accountProfiles) == 1){
					/** @var AccountProfile $accountProfile */
					$accountProfile = current($accountProfiles);
					$driver         = $accountProfile->driver;
				} else{
					die ("Multiple Account Profiles. Need handling for this");

					// TODO: build handling for multiple external systems when a user isn't logged in
//					global $configArray;
//					$driver = $configArray['Catalog']['driver'];
//					if ($accountProfile == null && !empty($driver)){
//						$accountProfile = new AccountProfile();
//						$accountProfile->get('driver', $driver);
//						// Another issue is that account profiles can also have the same driver
//						if (PEAR_Singleton::isError($accountProfile)){
//							$accountProfile = null;
//						}
//					}
				}
			}
		}

		if (isset(CatalogFactory::$catalogConnections[$driver])){
			return CatalogFactory::$catalogConnections[$driver];
		}else{
			require_once ROOT_DIR . '/CatalogConnection.php';
			CatalogFactory::$catalogConnections[$driver] = new CatalogConnection($driver, $accountProfile);
			return CatalogFactory::$catalogConnections[$driver];
		}
	}
}
