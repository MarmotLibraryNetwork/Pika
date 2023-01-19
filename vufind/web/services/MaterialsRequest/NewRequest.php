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

/**
 * MaterialsRequest Home Page, displays an existing Materials Request.
 */
class MaterialsRequest_NewRequest extends Action {

	function launch(){
		global /** @var Location $locationSingleton */
		$configArray,
		$interface,
		$library,
		$locationSingleton;

		if (!UserAccount::isLoggedIn()){
			header('Location: /MyAccount/Home?followupModule=MaterialsRequest&followupAction=NewRequest');
			exit;
		}else{
			// Hold Pick-up Locations
			$locations = $locationSingleton->getPickupBranches(UserAccount::getActiveUserObj(), UserAccount::getUserHomeLocationId());

			$pickupLocations = [];
			foreach ($locations as $key => $curLocation){
				if ($key != '0default'){
					$pickupLocations[] = [
						'id'          => $curLocation->locationId,
						'displayName' => $curLocation->displayName,
						'selected'    => $curLocation->selected,
					];
				}
			}
			$interface->assign('pickupLocations', $pickupLocations);

			//Get a list of formats to show
			$availableFormats = MaterialsRequest::getFormats();
			$interface->assign('availableFormats', $availableFormats);

			//Setup a default title based on the search term
			$interface->assign('new', true);
			$request                         = new MaterialsRequest();
			$request->placeHoldWhenAvailable = true; // set the place hold option on by default
			$request->illItem                = true; // set the place hold option on by default

			if (!empty($_REQUEST['lookfor'])){
				$searchType = $_REQUEST['basicType'] ?? $_REQUEST['type'] ?? 'Keyword';
				if (strcasecmp($searchType, 'author') == 0){
					$request->author = $_REQUEST['lookfor'];
				}else{
					$request->title = $_REQUEST['lookfor'];
				}
			}

			$user = UserAccount::getActiveUserObj();
			if ($user){
				$request->phone = trim(strip_tags(str_ireplace(['### TEXT ONLY', 'TEXT ONLY'], '', $user->phone)));
				if ($user->email != 'notice@salidalibrary.org'){
					$request->email = $user->email;
				}
			}

			$interface->assign('materialsRequest', $request);

			$interface->assign('showEbookFormatField', $configArray['MaterialsRequest']['showEbookFormatField']);
//			$interface->assign('showEaudioFormatField', $configArray['MaterialsRequest']['showEaudioFormatField']);
			$interface->assign('requireAboutField', $configArray['MaterialsRequest']['requireAboutField']);


			$useWorldCat = !empty($configArray['WorldCat']['apiKey']);
			$interface->assign('useWorldCat', $useWorldCat);

			if (isset($library)){
				// Get the Fields to Display for the form
				$requestFormFields = $request->getRequestFormFields($library->libraryId);
				$interface->assign('requestFormFields', $requestFormFields);

				// Add bookmobile Stop to the pickup locations if that form field is being used.
				foreach ($requestFormFields as $catagory){
					/** @var MaterialsRequestFormFields $formField */
					foreach ($catagory as $formField){
						if ($formField->fieldType == 'bookmobileStop'){
							$pickupLocations[] = array(
								'id'          => 'bookmobile',
								'displayName' => $formField->fieldLabel,
								'selected'    => false,
							);
							$interface->assign('pickupLocations', $pickupLocations);
							break 2;
						}
					}
				}

				// Get Author Labels for all Formats and Formats that use Special Fields
				list($formatAuthorLabels, $specialFieldFormats) = $request->getAuthorLabelsAndSpecialFields($library->libraryId);

				$interface->assign('formatAuthorLabelsJSON', json_encode($formatAuthorLabels));
				$interface->assign('specialFieldFormatsJSON', json_encode($specialFieldFormats));
			}

			$this->display('new.tpl', translate('Materials_Request_alt'));
		}
	}
}
