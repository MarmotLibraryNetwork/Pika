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
 * Created by PhpStorm.
 * User: jabedo
 * Date: 10/29/2016
 * Time: 8:10 AM
 * Handles an offer for a related record
 */
class LDRecordOffer {
	private $relatedRecord;

	public function __construct($record) {
		$this->relatedRecord = $record;
	}

	public function getOffers() {
		$offers = array();
		if (isset($this->relatedRecord['itemSummary'])){
			foreach ($this->relatedRecord['itemSummary'] as $itemData){
				if ($itemData['isLibraryItem'] || $itemData['isEContent']) {
					$offerData = array(
							"availability" => $this->getOfferAvailability($itemData),
							'availableDeliveryMethod' => $this->getDeliveryMethod(),
							"itemOffered" => array(
									'@type' => 'CreativeWork',
									'@id' => $this->getOfferLinkUrl(), //URL to the record
							),
							"offeredBy" => $this->getLibraryUrl(), //URL to the library that owns the item
							"price" => '0',
							"inventoryLevel" => $itemData['availableCopies'],
					);
					$locationCode = $itemData['locationCode'];
					$subLocation = $itemData['subLocation'];
					if (strlen($locationCode) > 0){
						$offerData['availableAtOrFrom'] = $this->getBranchUrl($locationCode, $subLocation);
					}
					$offers[] = $offerData;
				}
			}
		}

		return $offers;
	}

	public function getWorkType() {
		return $this->relatedRecord['schemaDotOrgType'] ?? null;
	}

	function getOfferLinkUrl() {
		global $configArray;
		return $configArray['Site']['url'] . $this->relatedRecord['url'];
	}

	function getLibraryUrl() {
		global $configArray;
		$offerBy = array();
		global $library;
		$offerBy[] = array(
				"@type" => "Library",
				"@id" => $configArray['Site']['url'] . "/Library/{$library->libraryId}/System",
				"name" => $library->displayName
		);
		return $offerBy;
	}

	function getOfferAvailability($itemData) {
		if ($itemData['inLibraryUseOnly']) {
			return 'InStoreOnly';
		}
		if ($this->relatedRecord['availableOnline']) {
			return 'OnlineOnly';
		}
		if ($itemData['availableCopies'] > 0) {
			return 'InStock';
		}

		if ($itemData['status'] != '') {
			switch (strtolower($itemData['status'])) {
				case 'on order':
				case 'in processing':
					$availability = 'PreOrder';
					break;
				case 'currently unavailable':
					$availability = 'Discontinued';
					break;
				default:
					$availability = 'InStock';
			}
			return $availability;
		}
		return "";
	}

	function getBranchUrl($locationCode, $subLocation) {
		global $configArray;
		global $library;
		$locations = new Location();
		$locations->libraryId = $library->libraryId;
		$locations->whereAdd("LEFT('$locationCode', LENGTH(code)) = code");
		if ($subLocation){
			$locations->subLocation = $subLocation;
		}
		$locations->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
		$locations->find();
		$subLocations = array();
		while ($locations->fetch()) {

			$subLocations[] = array(
					'@type' => 'Place',
					'name' => $locations->displayName,
					'@id' => $configArray['Site']['url'] . "/Library/{$locations->locationId}/Branch"

			);
		}
		return $subLocations;

	}

	function getDeliveryMethod() {
		if ($this->relatedRecord['isEContent']) {
			return 'DeliveryModeDirectDownload';
		} else {
			return 'DeliveryModePickUp';
		}

	}
}
