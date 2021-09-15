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

	public function getOffers(){
		$offers = [];
		if (isset($this->relatedRecord['itemSummary'])){
			foreach ($this->relatedRecord['itemSummary'] as $itemData){
				if ($itemData['isLibraryItem'] || $itemData['isEContent']){
					$offerData    = [
						'availability'            => $this->getOfferAvailability($itemData),
						'availableDeliveryMethod' => $this->getDeliveryMethod(),
						"itemOffered"             => [
							'@type' => 'CreativeWork',
							'@id'   => $this->getOfferLinkUrl(), //URL to the record
						],
						'offeredBy'               => $this->getLibraryUrl(), //URL to the library that owns the item
						'price'                   => '0',
						'inventoryLevel'          => $itemData['availableCopies'],
					];
					$locationCode = $itemData['locationCode'];
					if (strlen($locationCode) > 0){
						$offerData['availableAtOrFrom'] = $this->getBranchUrl($locationCode);
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
		global $library;
		return ($library->catalogUrl ?? $configArray['Site']['url']) . $this->relatedRecord['url'];
	}

	function getLibraryUrl(){
		$offerBy = [];
		global $configArray;
		global $library;
		$offerBy[] = [
			'@type' => 'Library',
			'@id'   => ($library->catalogUrl ?? $configArray['Site']['url']) . "/Library/{$library->libraryId}/System",
			'name'  => $library->displayName
		];
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
		return '';
	}

	function getBranchUrl($locationCode){
		/** @var Library $library */
		global $configArray;
		global $library;
		$branches             = [];
		$locations            = new Location();
		$locations->libraryId = $library->libraryId;
		$locations->whereAdd("LEFT('$locationCode', LENGTH(code)) = code");
		$locations->orderBy('isMainBranch DESC, displayName'); // List Main Branches first, then sort by name
		if ($locations->find()){
			while ($locations->fetch()){
				$branches[] = [
					'@type' => 'Place',
					'name'  => $locations->displayName,
					'@id'   => ($library->catalogUrl ?? $configArray['Site']['url']) . "/Library/{$locations->locationId}/Branch"
				];
			}
		}
		return $branches;
	}

	function getDeliveryMethod() {
		return $this->relatedRecord['isEContent'] ? 'DeliveryModeDirectDownload' : 'DeliveryModePickUp';
	}
}
