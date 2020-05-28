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

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Locations extends ObjectEditor {

	function getObjectType(){
		return 'Location';
	}

	function getToolName(){
		return 'Locations';
	}

	function getPageTitle(){
		return 'Locations (Branches)';
	}

	function getAllObjects(){
		//Look lookup information for display in the user interface
		$user = UserAccount::getLoggedInUser();

		$location = new Location();
		$location->orderBy('displayName');
		if (UserAccount::userHasRole('locationManager')){
			$location->locationId = $user->homeLocationId;
		}elseif (!UserAccount::userHasRole('opacAdmin')){
			//Scope to just locations for the user based on home library
			$patronLibrary       = $user->getHomeLibrary();
//			$patronLibrary       = Library::getLibraryForLocation($user->homeLocationId);
			$location->libraryId = $patronLibrary->libraryId;
		}
		$location->find();
		$locationList = array();
		while ($location->fetch()){
			$locationList[$location->locationId] = clone $location;
		}
		return $locationList;
	}

    function customListActions(){
        return array(
            array('label' => 'Clone Location', 'onclick' =>'Pika.Admin.cloneLocationFromSelection()'),
        );
    }

	function getObjectStructure(){
		return Location::getObjectStructure();
	}

	function getPrimaryKeyColumn(){
		return 'code';
	}

	function getIdKeyColumn(){
		return 'locationId';
	}

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'libraryManager', 'locationManager');
	}

	function canAddNew(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function canDelete(){
		$user = UserAccount::getLoggedInUser();
		return UserAccount::userHasRole('opacAdmin');
	}

	function copyDataFromLocation(){
		$locationId = $_REQUEST['id'];
		if (isset($_REQUEST['submit'])){
			$location             = new Location();
			$location->locationId = $locationId;
			$location->find(true);

			$locationToCopyFromId           = $_REQUEST['locationToCopyFrom'];
			$locationToCopyFrom             = new Location();
			$locationToCopyFrom->locationId = $locationToCopyFromId;
			$locationToCopyFrom->find(true);

			if (isset($_REQUEST['copyFacets'])){
				$location->clearFacets();

				$facetsToCopy = $locationToCopyFrom->facets;
				foreach ($facetsToCopy as $facetKey => $facet){
					$facet->locationId       = $locationId;
					$facet->id               = null;
					$facetsToCopy[$facetKey] = $facet;
				}
				$location->facets = $facetsToCopy;
			}
			if (isset($_REQUEST['copyBrowseCategories'])){
				$location->clearBrowseCategories();

				$browseCategoriesToCopy = $locationToCopyFrom->browseCategories;
				foreach ($browseCategoriesToCopy as $key => $category){
					$category->locationId         = $locationId;
					$category->id                 = null;
					$browseCategoriesToCopy[$key] = $category;
				}
				$location->browseCategories = $browseCategoriesToCopy;
			}

			$location->update();
			header("Location: /Admin/Locations?objectAction=edit&id=" . $locationId);
		}else{
			//Prompt user for the location to copy from
			$allLocations = $this->getAllObjects();

			unset($allLocations[$locationId]);
			foreach ($allLocations as $key => $location){

			}
			global $interface;
			$interface->assign('allLocations', $allLocations);
			$interface->assign('id', $locationId);
			$interface->setTemplate('../Admin/copyLocationFacets.tpl');
		}
	}

	function getInstructions(){
		return 'For more information about Location Setting configuration, see the <a href="https://docs.google.com/document/d/1DO7DfrslDm2DXUYul0hKAh8u5pGmjwyQABVdmb6javM/edit?ts=5696ca39">online documentation</a>.';
	}
}
