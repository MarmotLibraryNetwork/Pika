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
 *
 *
 * @category Pika
 * @package  PatronDrivers
 * @author   Chris Froese
 *
 *
 */
namespace Pika\PatronDrivers;

use MarcRecord;
use RecordDriverFactory;
use Location;
use Pika\SierraPatronListOperations;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/SierraPatronListOperations.php";

class Lion extends Sierra {

	use SierraPatronListOperations;

	public function __construct($accountProfile){
		parent::__construct($accountProfile);
		$this->logger->info('Using Pika\PatronDrivers\Lion.');
	}

	public function getSelfRegistrationFields(){
		global $library;
		// get library code
		$location            = new Location();
		$location->libraryId = $library->libraryId;
		$location->find(true);
		if (!$location){
			//return ['success'=>false, 'barcode'=>''];
		}
		$homeLibraryCode = $location->code;

		$fields   = array();
		$fields[] = [
			'property' => 'homelibrarycode',
			'type'     => 'hidden',
			'default'  => $homeLibraryCode
		];
		$fields[] = array(
			'property'    => 'firstname',
			'type'        => 'text',
			'label'       => 'First Name',
			'description' => 'Your first name',
			'maxLength'   => 40,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'lastname',
			'type'        => 'text',
			'label'       => 'Last Name',
			'description' => 'Your last name',
			'maxLength'   => 40,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'email',
			'type'        => 'email',
			'label'       => 'E-Mail',
			'description' => 'E-Mail (for confirmation, notices and newsletters)',
			'maxLength'   => 128,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'primaryphone',
			'type'        => 'text',
			'label'       => 'Phone Number',
			'description' => 'Phone Number',
			'maxLength'   => 12,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'address',
			'type'        => 'text',
			'label'       => 'Address',
			'description' => 'Address',
			'maxLength'   => 128,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'city',
			'type'        => 'text',
			'label'       => 'City',
			'description' => 'City',
			'maxLength'   => 48,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'state',
			'type'        => 'text',
			'label'       => 'State',
			'description' => 'State',
			'maxLength'   => 32,
			'required'    => true
		);
		$fields[] = array(
			'property'    => 'zip',
			'type'        => 'text',
			'label'       => 'Zip Code',
			'description' => 'Zip Code',
			'maxLength'   => 5,
			'required'    => true
		);
		return $fields;
	}

	function allowFreezingPendingHolds(){
		return true;
	}

	/**
	 * Get the holds for a patron
	 *
	 * GET patrons/$patronId/holds
	 *
	 * @param  User $patron
	 * @return array|bool
	 */
	public function getMyHolds($patron, $linkedAccount = false) {

		$patronHoldsCacheKey = $this->cache->makePatronKey('holds', $patron->id);
		if ($patronHolds = $this->cache->get($patronHoldsCacheKey)) {
			$this->logger->info("Found holds in memcache:" . $patronHoldsCacheKey);
			return $patronHolds;
		}

		if(!$patronId = $this->getPatronId($patron)) {
			return false;
		}

		$operation = "patrons/".$patronId."/holds";
		if((integer)$this->configArray['Catalog']['api_version'] > 4) {
			$params=["fields" => "default,pickupByDate,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate",
			         "limit"  => 1000];
		} else {
			$params=["fields" => "default,frozen,priority,priorityQueueLength,notWantedBeforeDate,notNeededAfterDate",
			         "limit"  => 1000];
		}
		$holds = $this->_doRequest($operation, $params);

		if(!$holds) {
			return false;
		}

		if($holds->total == 0) {
			return [
			 'available'   => [],
			 'unavailable' => []
			];
		}
		// these will be consistent for every hold
		$displayName  = $patron->getNameAndLibraryLabel();
		$pikaPatronId = $patron->id;
		// can we change pickup location?
		$pickupLocations = $patron->getValidPickupBranches($this->accountProfile->recordSource, false);
		// Need to exclude linked accounts here to prevent infinite loop during patron login in cases where accounts are reciprocally linked
		if(is_array($pickupLocations)) {
			if (count($pickupLocations) > 1) {
				$canUpdatePL = true;
			} else {
				$canUpdatePL = false;
			}
		} else {
			$canUpdatePL = false;
		}

		$availableHolds   = [];
		$unavailableHolds = [];
		foreach ($holds->entries as $hold) {
			// standard stuff
			$h['holdSource']      = $this->accountProfile->recordSource;
			$h['userId']          = $pikaPatronId;
			$h['user']            = $displayName;

			// get what's available from this call
			$h['frozen']                = $hold->frozen;
			$h['create']                = strtotime($hold->placed); // date hold created
			// innreach holds don't include notNeededAfterDate
			$h['automaticCancellation'] = isset($hold->notNeededAfterDate) ? strtotime($hold->notNeededAfterDate) : null; // not needed after date
			$h['expire']                = isset($hold->pickupByDate) ? strtotime($hold->pickupByDate) : false; // pick up by date // this isn't available in api v4

			// fix up hold position
			// #D-3420
			if (isset($hold->priority) && isset($hold->priorityQueueLength)) {
				// sierra api v4 priority is 0 based index so add 1
				if ($this->configArray['Catalog']['api_version'] == 4 ) {
					$holdPriority = (integer)$hold->priority + 1;
				} else {
					$holdPriority = $hold->priority;
				}
				$h['position'] = $holdPriority . ' of ' . $hold->priorityQueueLength;

			} elseif (isset($hold->priority) && !isset($hold->priorityQueueLength)) {
				// sierra api v4 priority is 0 based index so add 1
				if ($this->configArray['Catalog']['api_version'] == 4 ) {
					$holdPriority = (integer)$hold->priority + 1;
				} else {
					$holdPriority = $hold->priority;
				}
				$h['position'] = $holdPriority;
			} else {
				$h['position'] = false;
			}

			// cancel id
			preg_match($this->urlIdRegExp, $hold->id, $m);
			$h['cancelId'] = $m[1];

			// status, cancelable, freezable
			switch ($hold->status->code) {
				case '0':
					if($hold->frozen) {
						$status = "Frozen";
					} else {
						$status = 'On hold';
					}
					$cancelable = true;
					$freezeable = true;
					if($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					break;
				case 'b':
				case 'j':
				case 'i':
					$status       = 'Ready';
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				case 't':
					$status     = 'In transit';
					$cancelable = true;
					$freezeable = false;
					if($canUpdatePL) {
						$updatePickup = true;
					} else {
						$updatePickup = false;
					}
					break;
				case "&":
					$status       = "Requested from INN-Reach";
					$cancelable   = true;
					$freezeable   = false;
					$updatePickup = false;
					break;
				default:
					$status       = 'Unknown';
					$cancelable   = false;
					$freezeable   = false;
					$updatePickup = false;
			}
			// for sierra, holds can't be frozen if patron is next in line
			if(isset($hold->priorityQueueLength)) {
				if(isset($hold->priority) && ((int)$hold->priority <= 2 && (int)$hold->priorityQueueLength >= 2)) {
					$freezeable = false;
					// if the patron is the only person on wait list hold can't be frozen
				} elseif(isset($hold->priority) && ($hold->priority == 1 && (int)$hold->priorityQueueLength == 1)) {
					$freezeable = false;
					// if there is no priority set but queueLength = 1
				} elseif(!isset($hold->priority) && $hold->priorityQueueLength == 1) {
					$freezeable = false;
				}
			}
			$h['status']    = $status;
			$h['freezeable']= $freezeable;
			$h['cancelable']= $cancelable;
			$h['locationUpdateable'] = $updatePickup;
			// unset for next round.
			unset($status, $freezeable, $cancelable, $updatePickup);

			// pick up location
			if (!empty($hold->pickupLocation)){
				$pickupBranch = new Location();
				$where        = "code = '{$hold->pickupLocation->code}'";
				$pickupBranch->whereAdd($where);
				$pickupBranch->find(1);
				if ($pickupBranch->N > 0){
					$pickupBranch->fetch();
					$h['currentPickupId']   = $pickupBranch->locationId;
					$h['currentPickupName'] = $pickupBranch->displayName;
					$h['location']          = $pickupBranch->displayName;
				}else{
					$h['currentPickupId']   = false;
					$h['currentPickupName'] = $hold->pickupLocation->name;
					$h['location']          = $hold->pickupLocation->name;
				}
			} else{
				//This shouldn't happen but we have had examples where it did
				$this->logger->error("Patron with barcode {$patron->getBarcode()} has a hold with out a pickup location ");
				$h['currentPickupId']   = false;
				$h['currentPickupName'] = false;
				$h['location']          = false;
			}

			// determine if this is an innreach hold
			// or if it's a regular ILS hold
			if(strstr($hold->record, "@")) {
				///////////////
				// INNREACH HOLD
				///////////////
				// get the inn-reach item id
				$regExp = '/.*\/(.*)$/';
				// we have to query for the item status (it will be an innreach status) as hold status for
				// inn-reach will always show 0
				preg_match($regExp, $hold->record, $itemId);
				$itemParams    = ['fields'=>'status'];
				$itemOperation = 'items/'.$itemId[1];
				$itemRes = $this->_doRequest($itemOperation,$itemParams);
				if($itemRes) {
					if($itemRes->status->code != '&') {
						$h['cancelable']         = false;
					}
					if($itemRes->status->code == '#') {
						$hold->status->code = 'i';
						$h['status']             = 'Ready';
						$h['freezeable']         = false;
						$h['cancelable']         = false;
						$h['locationUpdateable'] = false;
					}
				}
				// get the hold id
				preg_match($this->urlIdRegExp, $hold->id, $mIr);
				$innReachHoldId = $mIr[1];

				$innReach = new InnReach();
				$titleAndAuthor = $innReach->getHoldTitleAuthor($innReachHoldId);
				$coverImage = $innReach->getInnReachCover();
				if(!$titleAndAuthor) {
					$h['title']     = 'Unknown';
					$h['author']    = 'Unknown';
					$h['sortTitle'] = '';
				} else {
					$h['title']     = $titleAndAuthor['title'];
					$h['author']    = $titleAndAuthor['author'];
					$h['sortTitle'] = $titleAndAuthor['sort_title'];
				}
				$h['freezeable']         = false;
				$h['locationUpdateable'] = false;
				$h['coverUrl']           = $coverImage;
			} else {
				///////////////
				// ILS HOLD
				//////////////
				// record type and record id
				$recordType = $hold->recordType;
				preg_match($this->urlIdRegExp, $hold->record,$m);
				// for item level holds we need to grab the bib id.
				$id = $m[1];
				if($recordType == 'i') {
					$id = $this->_getBibIdFromItemId($id);
				}
				// for Pika we need the check digit.
				$recordXD  = $this->getCheckDigit($id);

				// get more info from record
				$bibId = '.b'.$id.$recordXD;
				$recordSourceAndId = new \SourceAndId($this->accountProfile->recordSource . ":" . $bibId);
				$record = RecordDriverFactory::initRecordDriverById($recordSourceAndId);
				if ($record->isValid()){
					$h['id']              = $record->getUniqueID();
					$h['shortId']         = $record->getShortId();
					$h['title']           = $record->getTitle();
					$h['sortTitle']       = $record->getSortableTitle();
					$h['author']          = $record->getAuthor();
					$h['format']          = $record->getFormat();
					$h['link']            = $record->getRecordUrl();
					$h['coverUrl']        = $record->getBookcoverUrl('medium');
				};
			}
			if($hold->status->code == "b" || $hold->status->code == "j" || $hold->status->code == "i") {
				$availableHolds[] = $h;
			} else {
				$unavailableHolds[] = $h;
			}
			// unset for next loop
			unset($h);
		} // end foreach

		$return['available']   = $availableHolds;
		$return['unavailable'] = $unavailableHolds;
		// for linked accounts we might run into problems
		unset($availableHolds, $unavailableHolds);

		if(!$linkedAccount){
			$this->cache->set($patronHoldsCacheKey, $return, $this->configArray['Caching']['user_holds']);
		}

		return $return;
	}

}
