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
 * Displays Information about Places stored in the Digital Repository
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 8/7/2015
 * Time: 7:55 AM
 */

require_once ROOT_DIR . '/services/Archive/Entity.php';
class Archive_Place extends Archive_Entity{
	function launch(){
		global $interface;

		$this->loadArchiveObjectData();
		$this->recordDriver->loadLinkedData();
		$this->loadRelatedContentForEntity();

		$interface->assign('showExploreMore', true);

		/** @var PlaceDriver $placeDriver */
		$placeDriver = $this->recordDriver;
		$geoData = $placeDriver->getGeoData();
		$addressInfo = $interface->getVariable('addressInfo');
		if ($addressInfo == null || count($addressInfo) == 0 && $geoData != null){

			$addressInfo['latitude'] = $geoData['latitude'];
			$addressInfo['longitude'] = $geoData['longitude'];

			$interface->assign('addressInfo', $addressInfo);
		}


		// Display Page
		$this->display('baseArchiveObject.tpl');
	}
}
