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
 * Displays information about a particular library branch
 * Library in Schema.org terminology
 *
 * URL structure: [host url]/Library/[id number]/Branch
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/27/2016
 * Time: 2:30 PM
 */
class Branch extends Action {

	function launch(){
		global $interface;
		global $configArray;

		if (!empty($_REQUEST['id']) && ctype_digit($_REQUEST['id'])){
			$location             = new Location();
			$location->locationId = $_REQUEST['id'];
			if ($location->find(true)){
				$interface->assign('location', $location);

				$locationInfo = $location->getLocationInformation();
				$interface->assign('locationInfo', $locationInfo);

				//Schema.org
				$semanticData = [
					'@context'           => 'http://schema.org',
					'@type'              => 'Library',
					'name'               => $location->displayName,
					'branchCode'         => $location->code,
					'parentOrganization' => $configArray['Site']['url'] . "/Library/{$location->libraryId}/System"
				];

				if ($location->address){
					$semanticData['address'] = $location->address;
					$semanticData['hasMap']  = $locationInfo['map_link'];
				}
				if ($location->phone){
					$semanticData['telephone'] = $location->phone;
				}
				$hoursSemantic = [];
				foreach ($location->getHours() as $hourObj){
					if (!$hourObj->closed){
						$hoursSemantic[] = [
							'@type'     => 'OpeningHoursSpecification',
							'opens'     => $hourObj->open,
							'closes'    => $hourObj->close,
							'dayOfWeek' => 'http://purl.org/goodrelations/v1#' . $hourObj->day
						];
					}
				}
				if (!empty($hoursSemantic)){
					$semanticData['openingHoursSpecification'] = $hoursSemantic;
				}

				$interface->assign('semanticData', json_encode($semanticData));
			}
			$this->display('branch.tpl', $location->displayName);
		}
	}
}
