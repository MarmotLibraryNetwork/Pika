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

require_once ROOT_DIR . '/services/MyAccount/MyAccount.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequestStatus.php';

/**
 * MaterialsRequest Submission processing, processes a new request for the user and
 * displays a success/fail message to the user.
 */
class MaterialsRequest_Submit extends MyAccount {

	/**
	 * Remove any non-number character (except X) because ISBNs have an X check digit
	 *
	 * @param $string
	 * @return array|string|string[]|null
	 */
	private function removeNonNumbers($string){
		return preg_replace('/[^\dX]/i', '', $string);
	}

	function launch(){
		global $configArray;
		global $interface;
		global $library;

		$maxActiveRequests  = isset($library) ? $library->maxOpenRequests : 5;
		$maxRequestsPerYear = isset($library) ? $library->maxRequestsPerYear : 60;
		$accountPageLink    = '/MaterialsRequest/MyRequests';
		$interface->assign('accountPageLink', $accountPageLink);
		$interface->assign('maxActiveRequests', $maxActiveRequests);
		$interface->assign('maxRequestsPerYear', $maxRequestsPerYear);


		//Check to see if the user type is ok to submit a request
		$enableMaterialsRequest = true;
		if (isset($configArray['MaterialsRequest']['allowablePatronTypes'])){
			//Check to see if we need to do additional restrictions by patron type
			$allowablePatronTypes = $configArray['MaterialsRequest']['allowablePatronTypes'];
			$user                 = UserAccount::getLoggedInUser();
			if (strlen($allowablePatronTypes) > 0 && $user){
				if (!preg_match("/^$allowablePatronTypes$/i", $user->patronType)){
					$enableMaterialsRequest = false;
				}
			}
		}
		if (!$enableMaterialsRequest){
			$interface->assign('success', false);
			$interface->assign('error', 'Sorry, only residents may submit ' . translate('materials request') . 's at this time.');
		}else{
			if ($_REQUEST['format'] == 'article' && $_REQUEST['acceptCopyright'] != 1){
				$interface->assign('success', false);
				$interface->assign('error', 'Sorry, you must accept the copyright agreement before submitting a ' . translate('materials request') . '.');
			}else{
				//Check to see how many active materials request results the user has already.
				$materialsRequest            = new MaterialsRequest();
				$materialsRequest->createdBy = UserAccount::getActiveUserId();
				$statusQuery                 = new MaterialsRequestStatus();
				$homeLibrary                 = UserAccount::getUserHomeLibrary();
				$statusQuery->libraryId      = $homeLibrary->libraryId;
				$statusQuery->isOpen         = 1;
				$materialsRequest->joinAdd($statusQuery);
				$openRequests = $materialsRequest->count();
//				$materialsRequest->selectAdd();
//				$materialsRequest->selectAdd('materials_request.*, description as statusLabel');

				$materialsRequest->find();
				$interface->assign('openRequests', $openRequests);

				if ($materialsRequest->N >= $maxActiveRequests){
					$interface->assign('success', false);
					$materialsRequestString = translate('materials_request_short');
					$interface->assign('error', "You've already reached your maximum limit of $maxActiveRequests " . translate('materials request') . "s open at one time. Once we've processed your existing {$materialsRequestString}s, you'll be able to submit again. To check the status of your current {$materialsRequestString}s, visit your <a href='{$accountPageLink}'>account</a>.");
				}else{
					//Check the total number of requests created this year
					$materialsRequest            = new MaterialsRequest();
					$materialsRequest->createdBy = UserAccount::getActiveUserId();
					$materialsRequest->whereAdd('dateCreated >= unix_timestamp(now() - interval 1 year)');
					//To be fair, don't include any requests that were cancelled by the patron
					$statusQuery                 = new MaterialsRequestStatus();
					$statusQuery->isPatronCancel = 0;
					$materialsRequest->joinAdd($statusQuery);
					$requestsThisYear = $materialsRequest->count();
					$interface->assign('requestsThisYear', $requestsThisYear);
					if ($requestsThisYear >= $maxRequestsPerYear){
						$interface->assign('success', false);
						$materialsRequestString = translate('materials_request_short');
						$interface->assign('error', "You've already reached your maximum limit of $maxRequestsPerYear " . translate('materials request') . "s per year. To check the status of your current {$materialsRequestString}s, visit your <a href='{$accountPageLink}'>account page</a>.");
					}else{
						//Materials request can be submitted.
						$materialsRequest         = new MaterialsRequest();
						$materialsRequest->format = empty($_REQUEST['format']) ? '' : strip_tags($_REQUEST['format']);
						if (empty($materialsRequest->format)){
							$interface->assign('success', false);
							$interface->assign('error', 'No format was specified.');
						}else{
							$materialsRequest->phone                  = isset($_REQUEST['phone']) ? trim(strip_tags(str_ireplace(['### TEXT ONLY', 'TEXT ONLY'], '', $_REQUEST['phone']))) : '';
							$materialsRequest->email                  = isset($_REQUEST['email']) ? strip_tags($_REQUEST['email']) : '';
							$materialsRequest->title                  = isset($_REQUEST['title']) ? strip_tags($_REQUEST['title']) : '';
							$materialsRequest->season                 = isset($_REQUEST['season']) ? strip_tags($_REQUEST['season']) : '';
							$materialsRequest->magazineTitle          = isset($_REQUEST['magazineTitle']) ? strip_tags($_REQUEST['magazineTitle']) : '';
							$materialsRequest->magazineDate           = isset($_REQUEST['magazineDate']) ? strip_tags($_REQUEST['magazineDate']) : '';
							$materialsRequest->magazineVolume         = isset($_REQUEST['magazineVolume']) ? strip_tags($_REQUEST['magazineVolume']) : '';
							$materialsRequest->magazineNumber         = isset($_REQUEST['magazineNumber']) ? strip_tags($_REQUEST['magazineNumber']) : '';
							$materialsRequest->magazinePageNumbers    = isset($_REQUEST['magazinePageNumbers']) ? strip_tags($_REQUEST['magazinePageNumbers']) : '';
							$materialsRequest->author                 = empty($_REQUEST['author']) ? '' : strip_tags($_REQUEST['author']);
							$materialsRequest->ageLevel               = isset($_REQUEST['ageLevel']) ? strip_tags($_REQUEST['ageLevel']) : '';
							$materialsRequest->bookType               = isset($_REQUEST['bookType']) ? strip_tags($_REQUEST['bookType']) : '';
							$materialsRequest->isbn                   = isset($_REQUEST['isbn']) ? $this->removeNonNumbers(strip_tags($_REQUEST['isbn'])) : '';
							$materialsRequest->upc                    = isset($_REQUEST['upc']) ? $this->removeNonNumbers(strip_tags($_REQUEST['upc'])): '';
							$materialsRequest->issn                   = isset($_REQUEST['issn']) ? $this->removeNonNumbers(strip_tags($_REQUEST['issn'])) : '';
							$materialsRequest->oclcNumber             = isset($_REQUEST['oclcNumber']) ? strip_tags($_REQUEST['oclcNumber']) : '';
							$materialsRequest->publisher              = empty($_REQUEST['publisher']) ? '' : strip_tags($_REQUEST['publisher']);
							$materialsRequest->publicationYear        = empty($_REQUEST['publicationYear']) ? '' : strip_tags($_REQUEST['publicationYear']);
							$materialsRequest->about                  = empty($_REQUEST['about']) ? '' : strip_tags($_REQUEST['about']);
							$materialsRequest->comments               = empty($_REQUEST['comments']) ? '' : strip_tags($_REQUEST['comments']);
							$materialsRequest->placeHoldWhenAvailable = empty($_REQUEST['placeHoldWhenAvailable']) ? 0 : $_REQUEST['placeHoldWhenAvailable'];
							$materialsRequest->holdPickupLocation     = empty($_REQUEST['holdPickupLocation']) ? '' : $_REQUEST['holdPickupLocation'];
							$materialsRequest->bookmobileStop         = empty($_REQUEST['bookmobileStop']) ? '' : $_REQUEST['bookmobileStop'];
							$materialsRequest->illItem                = empty($_REQUEST['illItem']) ? 0 : $_REQUEST['illItem'];

							$materialsRequest->libraryId = $homeLibrary->libraryId;

							$formatObject = $materialsRequest->getFormatObject();
							if (!empty($formatObject->id)){
								$materialsRequest->formatId = $formatObject->id;
							}

							if (isset($_REQUEST['ebookFormat']) && $formatObject->hasSpecialFieldOption('Ebook format')){
								$materialsRequest->subFormat = strip_tags($_REQUEST['ebookFormat']);

							}else{
								if (isset($_REQUEST['eaudioFormat']) && $formatObject->hasSpecialFieldOption('Eaudio format')){
									$materialsRequest->subFormat = strip_tags($_REQUEST['eaudioFormat']);
								}
							}

							if (isset($_REQUEST['abridged'])){
								if ($_REQUEST['abridged'] == 'abridged'){
									$materialsRequest->abridged = 1;
								}elseif ($_REQUEST['abridged'] == 'unabridged'){
									$materialsRequest->abridged = 0;
								}else{
									$materialsRequest->abridged = 2; //Not applicable
								}
							}

							$defaultStatus            = new MaterialsRequestStatus();
							$defaultStatus->isDefault = 1;
							$defaultStatus->libraryId = $homeLibrary->libraryId;
							if (!$defaultStatus->find(true)){
								$interface->assign('success', false);
								$interface->assign('error', 'There was an error submitting your ' . translate('materials request') . ', could not determine the default status.');
							}else{
								$materialsRequest->status      = $defaultStatus->id;
								$materialsRequest->dateCreated = time();
								$materialsRequest->createdBy   = UserAccount::getActiveUserId();
								$materialsRequest->dateUpdated = time();

								if ($materialsRequest->insert()){
									$interface->assign('success', true);
									$interface->assign('materialsRequest', $materialsRequest);
									// Update Request Counts on success
									$interface->assign('requestsThisYear', ++$requestsThisYear);
									$interface->assign('openRequests', ++$openRequests);
								}else{
									$interface->assign('success', false);
									$interface->assign('error', 'There was an error submitting your ' . translate('materials request') . '.');
								}
							}
						}
					}
				}
			}
		}

		$this->display('submission-result.tpl', 'Submission Result');
	}
}
