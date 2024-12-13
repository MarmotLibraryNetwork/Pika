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

require_once ROOT_DIR . "/Action.php";
require_once ROOT_DIR . '/sys/MaterialsRequest/MaterialsRequest.php';

/**
 * MaterialsRequest Update Page, updates an existing materials request.
 */
class MaterialsRequest_Update extends Action {

	function launch(){
		global $configArray;
		global $interface;

		//Load the materials request to determine if it can be edited
		$materialsRequest     = new MaterialsRequest();
		$materialsRequest->id = $_REQUEST['id'];
		if (!$materialsRequest->find(true)){
			$materialsRequest = null;
			$requestUser      = false;
		}else{
			$requestUser     = new User();
			$requestUser->id = $materialsRequest->createdBy;
			if ($requestUser->find(true)){
				$interface->assign('requestUser', $requestUser);
			}else{
				$requestUser = false;
			}
		}

		//Make sure that the user is valid
		$processForm = true;
		$user        = UserAccount::getLoggedInUser();
		if ($materialsRequest == null){
			$interface->assign('success', false);
			$interface->assign('error', 'Sorry, we could not find a request with that id.');
			$processForm = false;
		}elseif (!UserAccount::isLoggedIn()){
			$interface->assign('error', 'Sorry, you must be logged in to update a materials request.');
			$processForm = false;
		}elseif (UserAccount::userHasRole('library_material_requests') && $requestUser && $requestUser->getHomeLibrary()->libraryId == $user->getHomeLibrary()->libraryId){
			//Ok to process because they are an admin for the user's home library
		}elseif ($user->id != $materialsRequest->createdBy){
			$interface->assign('error', 'Sorry, you do not have permission to update this materials request.');
			$processForm = false;
		}
		if ($processForm){
			//Materials request can be submitted.
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
				$materialsRequest->isbn                   = isset($_REQUEST['isbn']) ? $materialsRequest->removeNonNumbers(strip_tags($_REQUEST['isbn'])) : '';
				$materialsRequest->upc                    = isset($_REQUEST['upc']) ? $materialsRequest->removeNonNumbers(strip_tags($_REQUEST['upc'])) : '';
				$materialsRequest->issn                   = isset($_REQUEST['issn']) ? $materialsRequest->removeNonNumbers(strip_tags($_REQUEST['issn'])) : '';
				$materialsRequest->oclcNumber             = isset($_REQUEST['oclcNumber']) ? strip_tags($_REQUEST['oclcNumber']) : '';
				$materialsRequest->publisher              = empty($_REQUEST['publisher']) ? '' : strip_tags($_REQUEST['publisher']);
				$materialsRequest->publicationYear        = empty($_REQUEST['publicationYear']) ? '' : strip_tags($_REQUEST['publicationYear']);
				$materialsRequest->about                  = empty($_REQUEST['about']) ? '' : strip_tags($_REQUEST['about']);
				$materialsRequest->comments               = empty($_REQUEST['comments']) ? '' : strip_tags($_REQUEST['comments']);
				$materialsRequest->staffComments          = empty($_REQUEST['staffComments']) ? '' : strip_tags($_REQUEST['staffComments']);
				$materialsRequest->placeHoldWhenAvailable = empty($_REQUEST['placeHoldWhenAvailable']) ? 0 : $_REQUEST['placeHoldWhenAvailable'];
				$materialsRequest->holdPickupLocation     = empty($_REQUEST['holdPickupLocation']) ? '' : $_REQUEST['holdPickupLocation'];
				$materialsRequest->bookmobileStop         = empty($_REQUEST['bookmobileStop']) ? '' : $_REQUEST['bookmobileStop'];
				$materialsRequest->illItem                = empty($_REQUEST['illItem']) ? 0 : $_REQUEST['illItem'];

				$materialsRequest->libraryId = $requestUser->getHomeLibrary()->libraryId;

				$formatObject = $materialsRequest->getFormatObject();
				if (!empty($formatObject->id)){
					$materialsRequest->formatId = $formatObject->id;
				}

				if (isset($_REQUEST['ebookFormat']) && $formatObject->hasSpecialFieldOption('Ebook format')){
					$materialsRequest->subFormat = strip_tags($_REQUEST['ebookFormat']);

				}elseif (isset($_REQUEST['eaudioFormat']) && $formatObject->hasSpecialFieldOption('Eaudio format')){
					$materialsRequest->subFormat = strip_tags($_REQUEST['eaudioFormat']);
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
				$materialsRequest->dateUpdated = time();

				if ($materialsRequest->update()){
					$interface->assign('success', true);
					$interface->assign('materialsRequest', $materialsRequest);


					if (isset($_REQUEST['status']) && ctype_digit($_REQUEST['status'])){
						if (UserAccount::userHasRole('library_material_requests')){
							if (empty($materialsRequest->assignedTo)){
								$materialsRequest->assignedTo = $user->id;
								//updateRequestStatus() below will save this to the db for us automatically
							}
							$error = $materialsRequest->updateRequestStatus($_REQUEST['status']);
							if (is_string($error)){
								$interface->assign('error', $error);
							}
						}
					}

				}else{
					$interface->assign('success', false);
					$interface->assign('error', 'There was an error updating the materials request.');
				}
			}
		}else{
			$interface->assign('success', false);
			$interface->assign('error', 'Sorry, we could not find a request with that id.');
		}

		//Get a list of formats to show
		$availableFormats = MaterialsRequest::getFormats();
		$interface->assign('availableFormats', $availableFormats);
		$interface->assign('showEbookFormatField', $configArray['MaterialsRequest']['showEbookFormatField']);
//		$interface->assign('showEaudioFormatField', $configArray['MaterialsRequest']['showEaudioFormatField']);

		$this->display('update-result.tpl', 'Update Result');
	}
}
