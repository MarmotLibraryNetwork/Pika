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

use Pika\CirculationSystemDrivers\SierraDNA;

require_once ROOT_DIR . '/AJAXHandler.php';

class Admin_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = [
		'getAddToWidgetForm',
		'copyHooplaSettingsFromLibrary',
		'copyHooplaSettingsFromLocation',
		'clearLocationHooplaSettings',
		'clearLibraryHooplaSettings',
		'displayCopyFromPrompt',
		'displayClonePrompt',
		'libraryClonePrompt',
		'copyHourSettingsFromLocation',
		'copyBrowseCategoriesFromLocation',
		'copyIncludedRecordsFromLocation',
		'copyFullRecordDisplayFromLocation',
		'resetFacetsToDefault',
		'resetMoreDetailsToDefault',
		'copyFacetSettingsFromLocation',
		'cloneLocation',
		'cloneLibrary',
		'fileExists',
		'loadPtypes',
		'setCatalogUrlPrompt',
		'setCatalogUrl',
	];

	function getAddToWidgetForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		// Display Page
		$interface->assign('id', strip_tags($_REQUEST['id']));
		$interface->assign('source', strip_tags($_REQUEST['source']));
		require_once ROOT_DIR . '/sys/Widgets/ListWidget.php';
		$listWidget = new ListWidget();
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager'])){
			//Get all widgets for the library
			$userLibrary           = UserAccount::getUserHomeLibrary();
			$listWidget->libraryId = $userLibrary->libraryId;
		}
		$listWidget->orderBy('name');
		$existingWidgets = $listWidget->fetchAll('id', 'name');
		$interface->assign('existingWidgets', $existingWidgets);
		$results = [
			'title'        => 'Create a Widget',
			'modalBody'    => $interface->fetch('Admin/addToWidgetForm.tpl'),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#bulkAddToList\").submit();'>Create Widget</button>",
		];
		return $results;
	}

	/**
	 * Ajax class which calls copyLibraryHooplaSettings in order to copy the parent library's hoopla settings
	 *
	 * @return false|string[] if no value is returned a value of false will be returned
	 */
	function copyHooplaSettingsFromLibrary(){
		$results = [
			'title' => 'Copy Library Hoopla Settings',
			'body'  => '<div class="alert alert-danger">There was an error.</div>',
		];

		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$locationId = trim($_REQUEST['id']);
			if (ctype_digit($locationId)){
				$location = new Location();
				if ($location->get($locationId)){
					$location->clearHooplaSettings();
					if ($location->copyLibraryHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings copied successfully.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">At least one Hoopla setting failed to copy.</div>';
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Ajax class which calls CopyLocationHooplaSettings in order to copy a selected location's hoopla settings
	 *
	 * @return string[] returns a string array whether the result was successful or not
	 */
	function copyHooplaSettingsFromLocation(){
		$results = [
			'title' => 'Copy Location Hoopla Settings',
			'body'  => '<div class="alert alert-danger">There was an error.</div>',
		];

		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$locationId     = trim($_REQUEST['id']);
			$locationFromId = trim($_REQUEST['fromId']);
			if (ctype_digit($locationId) && ctype_digit($locationFromId)){
				$location = new Location();
				if ($location->get($locationId)){
					$location->clearHooplaSettings();
					if ($location->copyLocationHooplaSettings($locationFromId)){
						$results['body'] = '<div class="alert alert-success">Hoopla settings copied successfully.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">At least one Hoopla setting failed to copy.</div>';
					}
				}
			}
		}
		return $results;
	}

	function clearLocationHooplaSettings(){
		$results = [
			'title' => 'Clear Location Hoopla Settings',
			'body'  => '<div class="alert alert-danger">There was an error.</div>',
		];

		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$locationId = trim($_REQUEST['id']);
			if (ctype_digit($locationId)){
				$location = new Location();
				if ($location->get($locationId)){

					if ($location->clearHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings were cleared.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">Hoopla settings failed to clear.</div>';
					}
				}
			}
		}
		return $results;
	}

	function clearLibraryHooplaSettings(){
		$results = [
			'title' => 'Clear Library Hoopla Settings',
			'body'  => '<div class="alert alert-danger">There was an error.</div>',
		];

		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$libraryId = trim($_REQUEST['id']);
			if (ctype_digit($libraryId)){
				$library = new Library();
				if ($library->get($libraryId)){

					if ($library->clearHooplaSettings()){
						$results['body'] = '<div class="alert alert-success">Hoopla settings were cleared.</div>';
					}else{
						$results['body'] = '<div class="alert alert-danger">Hoopla settings failed to clear.</div>';
					}
				}
			}
		}
		return $results;
	}

	function displayClonePrompt(){
		$results = [
			'title' => 'Clone Location',
			'body'  => 'No Data available',
		];
		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$command    = trim($_REQUEST['command']);
			$location   = new Location();
			$locationId = $user->getHomeLibrary()->getLocationIdsForLibrary()[0];
			if ($location->get($locationId)){
				$allLocations = $this->getLocationList($locationId);
				$options      = " ";

				foreach ($allLocations as $findKey => $findLocation){
					$options .= "<option value='" . $findKey . "'>" . $findLocation->displayName . "</option>";
				}
				$results['body']    = "<label for='code'>New Location Code:</label> <input type='text' class='form-control' id='LocCode' name='LocCode'/><label for='name'>Display Name</label> <input type='text' class='form-control' id='name' name='name' /><label for='fromId'>Clone From</label> <select id= 'fromId' name='fromId' class='form-control'>" . $options . "</select>";
				$results['buttons'] = "<button class='btn btn-primary' type= 'button' title='Copy' onclick='return Pika.Admin." . $command . "(document.querySelector(\"#fromId\").value, document.querySelector(\"#name\").value, document.querySelector(\"#LocCode\").value);'>Clone</button>";

			}
		}
		return $results;
	}

	function setCatalogUrlPrompt(){
		$isLocation              = !empty($_REQUEST['isLocation']) && $_REQUEST['isLocation'] == 1;
		$libraryOrLocationString = $isLocation ? 'Location' : 'Library';
		$results                 = [
			'title' => "Set $libraryOrLocationString Catalog URL",
			'body'  => 'You do not have permission to use this action.',
		];
		/** @var User $user */
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin')){
			$id = trim($_REQUEST['id']);
			if (!empty($id) && ctype_digit($id)){
				$url     = '';
				$libraryOrLocation = $isLocation ? new Location() : new Library();
				if ($libraryOrLocation->get($id)){
					$url = $libraryOrLocation->catalogUrl;
				}
				global $interface, $configArray;
				$interface->assign([
					'id'         => $id,
					'catalogUrl' => $url,
					'isLocation' => $isLocation,
				]);
				if (!empty($configArray['Site']['isDevelopment'])){
					$interface->assign('isDevelopment', true);
				}
				$results['body']    = $interface->fetch('Admin/setCatalogUrlPrompt.tpl');
				$results['buttons'] = "<button class='tool btn btn-primary' onclick='$(\"#catalogUrlForm\").submit()'>Set Catalog URL</button>";
			}else{
				$results['body'] = "Invalid $libraryOrLocationString Id";
			}
		}
		return $results;
	}

	function setCatalogUrl(){
		$isLocation              = !empty($_REQUEST['isLocation']) && $_REQUEST['isLocation'] == 1;
		$libraryOrLocationString = $isLocation ? 'Location' : 'Library';
		$results                 = [
			'title'   => "Set $libraryOrLocationString Catalog URL",
			'body'    => 'You do not have permission to use this action.',
			'success' => false,
		];
		/** @var User $user */
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole('opacAdmin')){
			$id = trim($_REQUEST['id']);
			if (!empty($id) && ctype_digit($id)){
				$libraryOrLocation = $isLocation ? new Location() : new Library();
				if ($libraryOrLocation->get($id)){
					$newUrl           = trim($_REQUEST['catalogUrl']);
					$emptyNewUrl      = empty($newUrl);
					$emptyLocationUrl = $isLocation && $emptyNewUrl;
					if (!$emptyNewUrl || $emptyLocationUrl){ // URL required for libraries; optional for locations
						$currentUrl = $libraryOrLocation->catalogUrl;
						if ($newUrl != $currentUrl){
							$duplicateUrl = false;
							if (!$emptyLocationUrl){
								$checkLibraries             = new Library();
								$checkLibraries->catalogUrl = $newUrl;
								if (!$isLocation){
									$checkLibraries->whereAdd("libraryId != $id");
								}
								$duplicateUrl = $checkLibraries->find(true);
							}
							if (!$duplicateUrl/* || $emptyLocationUrl*/){
								if (!$emptyLocationUrl){
									$checkLocations             = new Location();
									$checkLocations->catalogUrl = $newUrl;
									if ($isLocation){
										$checkLocations->whereAdd("locationId != $id");
									}
									$duplicateUrl = $checkLocations->find(true);
								}
								if (!$duplicateUrl/* || $emptyLocationUrl*/){
									// Get site name from covers directory
									global $configArray;
									$partParts                     = explode('/', $configArray['Site']['coverPath']);
									$siteName                      = $partParts[count($partParts) - 2];
									$libraryOrLocation->catalogUrl = $emptyNewUrl ? "null" : $newUrl; // Set here for eventual updating after symbolic link actions are completed.
									if ($newUrl != $siteName){
										$localPath   = $configArray['Site']['local'];
										$sitesPath   = realpath("$localPath/../../sites/");
										$currentLink = "$sitesPath\\$currentUrl";
										$linkTarget  = "$sitesPath\\$siteName";
										$makeNewLink = !$emptyNewUrl;
										$linkName    = $makeNewLink ?  "$sitesPath\\$newUrl" : null;
										$linkRemoved = '';
										if ($currentLink != $linkTarget){ // Don't remove if we are using the site name Folder
											if (!empty($currentUrl) && file_exists($currentLink)){
												if (is_link($currentLink)){
//												if (stripos(PHP_OS, 'win') !== false){
//													$removalSuccess = shell_exec("rmdir $linkName");
//													$removalSuccess = shell_exec("rd $linkName");
//												} else{
													$removalSuccess = @unlink($currentLink);
													$linkRemoved = '<br>Previous symbolic link ' . $currentLink . ($removalSuccess ? ' was removed': ' was <strong>not</strong> removed');
													// Doesn't seem to work under windows. Have to remove old links by hand
//												}
												}else{
													$linkRemoved = "<br>Previous symbolic link $currentLink is not a symbolic link. It was <strong>not</strong> removed";
												}
											}
										}
										if ($makeNewLink){
											if (!file_exists($linkName)){
												$success = symlink($linkTarget, $linkName);
												if ($success){
													$libraryOrLocation->update();
													$results['success'] = true;
													$results['body']    = "URL <a href=\"{$_SERVER['REQUEST_SCHEME']}://$newUrl\" target=\"_blank\">$newUrl</a> successfully updated. $linkRemoved";
												}else{
													$results['body'] = "Failed to created symbolic link $linkName $linkRemoved";
												}
											}else{
												$libraryOrLocation->update();
												$results['body']    = "<a href=\"{$_SERVER['REQUEST_SCHEME']}://$newUrl\" target=\"_blank\">$linkName</a> already exists.  Did not create a new symbolic link. $linkRemoved.";
												$results['success'] = true;
											}
										} elseif ($emptyLocationUrl){
											$libraryOrLocation->update();
											$results['body']    = "URL unset. $linkRemoved.";
											$results['success'] = true;
										}
									}else{
										$libraryOrLocation->update();
										$results['body']    = "The catalog url <a href=\"{$_SERVER['REQUEST_SCHEME']}://$newUrl\" target=\"_blank\">$newUrl</a> is the same as the site name $siteName.  Did not create a symbolic link.";
										$results['success'] = true;
									}
								} else {
									$results['body'] = "The catalog url <a href=\"{$_SERVER['REQUEST_SCHEME']}://$newUrl\" target=\"_blank\">$newUrl</a> is already set for location {$checkLocations->displayName}";
								}
							}else{
								$results['body'] = "The catalog url <a href=\"{$_SERVER['REQUEST_SCHEME']}://$newUrl\" target=\"_blank\">$newUrl</a> is already set for library {$checkLibraries->displayName}";
							}
						}else{
							$results['body'] = 'Same as current URL. No Action taken.';
						}
					}else{
						$results['body'] = 'No URL provided.';
					}
				}else{
					$results['body'] = "Invalid $libraryOrLocationString Id.";
				}
			}else{
				$results['body'] = "Invalid $libraryOrLocationString Id.";
			}
		}
		return $results;
	}


	//							if (stripos(PHP_OS, 'linux') !== false){
//								$commandToRun = "ln -s $linkTarget $linkName";
//								$result       = shell_exec($commandToRun);
//							}elseif (stripos(PHP_OS, 'win') !== false){
//								$commandToRun = "mklink /D $linkName $linkTarget";
//								$result       = shell_exec($commandToRun);
//								if (stripos($result, 'symbolic link created') !== false){
//									// Success
//									$library->update();
//									$results['body'] = "URL Successfully updated.";
//								}
//							}


	function libraryClonePrompt(){
		$results = [
			'title' => 'Clone Library',
			'body'  => 'No Data available',
		];
		/** @var User $user */
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin'])){
			$command = trim($_REQUEST['command']);
			$library = $user->getHomeLibrary();
			if ($library){
				$allLibraries = $this->getLibraryList($library->libraryId);
				$options      = " ";

				foreach ($allLibraries as $findKey => $findLibrary){
					$options .= "<option value='" . $findKey . "'>" . $findLibrary->displayName . "</option>";
				}
				$results['body']    = "<label for='displayName'>Display Name:</label> <input type='text' class='form-control required' id='displayName' name='displayName'/><label for='subdomain'>Subdomain:</label> <input type='text' class='form-control required' id='subdomain' name='subdomain' /><label for='abName'>Abbreviated Name:</label> <input type='text' class='form-control' id='abName' name='abName' /><label for='facetLabelInput'>Library System Facet Label:</label> <input type='text' class='form-control' id='facetLabelInput' name='facetLabelInput' /><label for='fromId'>Clone From</label> <select id= 'fromId' name='fromId' class='form-control required'>" . $options . "</select>";
				$results['buttons'] = "<button class='btn btn-primary' type= 'button' title='Copy' onclick='return Pika.Admin." . $command . "(document.querySelector(\"#fromId\").value, document.querySelector(\"#displayName\").value, document.querySelector(\"#subdomain\").value, document.querySelector(\"#abName\").value, document.querySelector(\"#facetLabelInput\").value );'>Clone</button>";

			}
		}
		return $results;
	}

	/**
	 * Displays list of library locations to the user in order to select the location to copy from
	 * @return string[] select box with buttons to choose copy location
	 */
	function displayCopyFromPrompt(){
		$results = [
			'title' => 'Select Location to Copy From',
			'body'  => 'Copy was unsuccessful',

		];
		$user    = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$locationId = trim($_REQUEST['id']);
			$command    = trim($_REQUEST['command']);
			if (ctype_digit($locationId)){
				$location = new Location();
				if ($location->get($locationId)){

					$allLocations = $this->getLocationList($locationId);

					unset($allLocations[$locationId]);
					$options = " ";
					foreach ($allLocations as $findKey => $findLocation){
						$options .= "<option value='" . $findKey . "'>" . $findLocation->displayName . "</option>";
					}


					$results['body']    = "<select id= 'fromId' name='fromId' class='form-control'>" . $options . "</select>";
					$results['buttons'] = "<button class='btn btn-primary' type= 'button' title='Copy' onclick='return Pika.Admin." . $command . "(" . $locationId . ", document.querySelector(\"#fromId\").value);'>Copy</button>";
				}
			}
		}
		return $results;
	}

	/**
	 * Gets locations available to user to copy from for the logged in administrative user
	 *
	 * @param $locationId id of homeLocation
	 * @return array of available locations
	 */
	private function getLocationList($locationId){
		//Look lookup information for display in the user interface
		$user   = UserAccount::getLoggedInUser();
		$copyTo = new Location();

		$copyTo->get($locationId);
		$copyToLibrary = $copyTo->libraryId;
		unset($copyTo);
		$location = new Location();
		$location->orderBy('displayName');
		if (UserAccount::userHasRole('locationManager')){
			$location->locationId = $user->homeLocationId;
		}elseif (!UserAccount::userHasRole('opacAdmin')){
			//Scope to just locations for the user based on home library
			$patronLibrary       = $user->getHomeLibrary();
			$location->libraryId = $patronLibrary->libraryId;
		}
		$copyTo            = new Location();
		$copyTo->libraryId = $copyToLibrary;
		$copyTo->find();

		$location->find();

		$locationList = [];

		while ($copyTo->fetch()){

			$locationList[$copyTo->locationId] = clone $copyTo;

		}
		while ($location->fetch()){

			$locationList[$location->locationId] = clone $location;
		}


		return $locationList;
	}

	function getLibraryList($libraryId){
		//Look lookup information for display in the user interface
		$user = UserAccount::getLoggedInUser();

		$library = new Library();
		$library->orderBy('displayName');

		$copyTo            = new Library();
		$copyTo->libraryId = $libraryId;
		$copyTo->find();

		$library->find();

		$libraryList = [];

		while ($copyTo->fetch()){

			$libraryList[$copyTo->libraryId] = clone $copyTo;

		}
		while ($library->fetch()){

			$libraryList[$library->libraryId] = clone $library;
		}
		return $libraryList;
	}

	/**
	 * Ajax method copies hours between library locations
	 *
	 * @return string[] array containing the title and body displayed in the popup
	 */
	function copyHourSettingsFromLocation(){
		$results          = [
			'title' => 'Copy Hours From Location',
			'body'  => '<div class="alert alert-danger">Copy was unsuccessful</div>',
		];
		$user             = UserAccount::getLoggedInUser();
		$locationId       = trim($_REQUEST['id']);
		$fromLocationId   = trim($_REQUEST['fromId']);
		$location         = new Location();
		$copyFromLocation = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId) && $copyFromLocation->get($fromLocationId)){

				$copyFromLocation->getHours();
				$hoursToCopy = $copyFromLocation->hours;
				foreach ($hoursToCopy as $key => $hour){

					$hour->locationId  = $locationId;
					$hour->id          = null;
					$hoursToCopy[$key] = $hour;
				}
				$location->clearHours();
				$location->hours = $hoursToCopy;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Hours successfully copied.</div>';

			}
		}
		return $results;
	}

	/**
	 * Ajax method copies Browse Categories and settings related to them between library locations
	 *
	 * @return string[] array containing the title and body displayed in the popup
	 */
	function copyBrowseCategoriesFromLocation(){
		$results          = [
			'title' => 'Copy Browse Categories From Location',
			'body'  => '<div class="alert alert-danger">Copy was unsuccessful</div>',
		];
		$user             = UserAccount::getLoggedInUser();
		$locationId       = trim($_REQUEST['id']);
		$fromLocationId   = trim($_REQUEST['fromId']);
		$location         = new Location();
		$copyFromLocation = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId) && $copyFromLocation->get($fromLocationId)){
				$location->clearBrowseCategories();

				$browseCategoriesToCopy = $copyFromLocation->browseCategories;
				foreach ($browseCategoriesToCopy as $key => $category){
					$category->locationId         = $locationId;
					$category->id                 = null;
					$browseCategoriesToCopy[$key] = $category;
				}
				$location->browseCategories          = $browseCategoriesToCopy;
				$location->defaultBrowseMode         = $copyFromLocation->defaultBrowseMode;
				$location->browseCategoryRatingsMode = $copyFromLocation->browseCategoryRatingsMode;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Browse Categories successfully copied.</div>';
			}
		}
		return $results;
	}

	/**
	 * Ajax method copies the Facet settings between library locations
	 *
	 * @return string[] array containing the title and body displayed in the popup
	 */
	function copyFacetSettingsFromLocation(){
		$results          = [
			'title' => 'Copy Facets From Location',
			'body'  => '<div class="alert alert-danger">Copy was unsuccessful</div>',
		];
		$user             = UserAccount::getLoggedInUser();
		$locationId       = trim($_REQUEST['id']);
		$fromLocationId   = trim($_REQUEST['fromId']);
		$location         = new Location();
		$copyFromLocation = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId) && $copyFromLocation->get($fromLocationId)){
				$location->clearFacets();

				$facetsToCopy = $copyFromLocation->facets;
				foreach ($facetsToCopy as $facetKey => $facet){
					$facet->locationId       = $locationId;
					$facet->id               = null;
					$facetsToCopy[$facetKey] = $facet;
				}
				$location->baseAvailabilityToggleOnLocalHoldingsOnly   = $copyFromLocation->baseAvailabilityToggleOnLocalHoldingsOnly;
				$location->includeOnlineMaterialsInAvailableToggle     = $copyFromLocation->includeOnlineMaterialsInAvailableToggle;
				$location->includeAllLibraryBranchesInFacets           = $copyFromLocation->includeAllLibraryBranchesInFacets;
				$location->additionalLocationsToShowAvailabilityFor    = $copyFromLocation->additionalLocationsToShowAvailabilityFor;
				$location->includeAllRecordsInShelvingFacets           = $copyFromLocation->includeAllRecordsInShelvingFacets;
				$location->includeAllRecordsInDateAddedFacets          = $copyFromLocation->includeAllRecordsInDateAddedFacets;
				$location->includeOnOrderRecordsInDateAddedFacetValues = $copyFromLocation->includeOnOrderRecordsInDateAddedFacetValues;
				$location->facets                                      = $facetsToCopy;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Facets successfully copied.</div>';
			}
		}
		return $results;
	}

	/**
	 * Ajax method copies the Included Records between library locations
	 *
	 * @return string[] array containing the title and body displayed in the popup
	 */
	function copyIncludedRecordsFromLocation(){
		$results          = [
			'title' => 'Copy Included Records From Location',
			'body'  => '<div class="alert alert-danger">Copy was unsuccessful</div>',
		];
		$user             = UserAccount::getLoggedInUser();
		$locationId       = trim($_REQUEST['id']);
		$fromLocationId   = trim($_REQUEST['fromId']);
		$location         = new Location();
		$copyFromLocation = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId) && $copyFromLocation->get($fromLocationId)){

				$location->clearLocationRecordsToInclude();

				$includedToCopy = $copyFromLocation->recordsToInclude;
				foreach ($includedToCopy as $key => $include){
					$include->locationId  = $locationId;
					$include->id          = null;
					$includedToCopy[$key] = $include;
				}
				$location->recordsToInclude = $includedToCopy;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Records To Include successfully copied.</div>';
			}
		}
		return $results;
	}

	/**
	 * Ajax method Copies the Full Record Display Options between library locations
	 *
	 * @return string[] array containing the title and body displayed in the popup
	 */
	function copyFullRecordDisplayFromLocation(){

		$results          = [
			'title' => 'Copy Full Record Display From Location',
			'body'  => '<div class="alert alert-danger">Copy was unsuccessful</div>',
		];
		$user             = UserAccount::getLoggedInUser();
		$locationId       = trim($_REQUEST['id']);
		$fromLocationId   = trim($_REQUEST['fromId']);
		$location         = new Location();
		$copyFromLocation = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId) && $copyFromLocation->get($fromLocationId)){
				$location->clearMoreDetailsOptions();
				$fullRecordDisplayToCopy = $copyFromLocation->moreDetailsOptions;
				foreach ($fullRecordDisplayToCopy as $key => $displayItem){
					$displayItem->locationId       = $locationId;
					$displayItem->id               = null;
					$fullRecordDisplayToCopy[$key] = $displayItem;
				}
				$location->moreDetailsOptions       = $fullRecordDisplayToCopy;
				$location->showEmailThis            = $copyFromLocation->showEmailThis;
				$location->showShareOnExternalSites = $copyFromLocation->showShareOnExternalSites;
				$location->showComments             = $copyFromLocation->showComments;
				$location->showQRCode               = $copyFromLocation->showQRCode;
				$location->showStaffView            = $copyFromLocation->showStaffView;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Full Record Display successfully copied.</div>';
			}
		}

		return $results;
	}

	function resetFacetsToDefault(){
		$results    = [
			'title' => 'Reset Facets To Default',
			'body'  => '<div class="alert alert-danger">Reset was unsuccessful</div>',
		];
		$user       = UserAccount::getLoggedInUser();
		$locationId = trim($_REQUEST['id']);
		$location   = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId)){
				$location->clearFacets();

				$defaultFacets = Location::getDefaultFacets($locationId);

				$location->facets = $defaultFacets;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Facets Reset to Default.</div>';
			}
		}

		return $results;
	}

	function resetMoreDetailsToDefault(){
		$results    = [
			'title' => 'Reset Facets To Default',
			'body'  => '<div class="alert alert-danger">Reset was unsuccessful</div>',
		];
		$user       = UserAccount::getLoggedInUser();
		$locationId = trim($_REQUEST['id']);
		$location   = new Location();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			if ($location->get($locationId)){
				$location->clearMoreDetailsOptions();

				$defaultOptions = [];
				require_once ROOT_DIR . '/RecordDrivers/Interface.php';
				$defaultMoreDetailsOptions = RecordInterface::getDefaultMoreDetailsOptions();
				$i                         = 0;
				foreach ($defaultMoreDetailsOptions as $source => $defaultState){
					$optionObj                    = new LocationMoreDetails();
					$optionObj->locationId        = $locationId;
					$optionObj->collapseByDefault = $defaultState == 'closed';
					$optionObj->source            = $source;
					$optionObj->weight            = $i++;
					$defaultOptions[]             = $optionObj;
				}

				$location->moreDetailsOptions = $defaultOptions;
				$location->update();
				$results['body'] = '<div class="alert alert-success">Full Record Display reset to default.</div>';

			}
		}

		return $results;
	}

	function cloneLocation(){
		$results          = [
			'title' => 'Clone Location',
			'body'  => '<div class="alert alert-danger">Clone Failed</div>',
		];
		$fromLocationId   = trim($_REQUEST['from']);
		$code             = trim($_REQUEST['code']);
		$name             = trim($_REQUEST['name']);
		$copyFromLocation = new Location();
		$user             = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRoleFromList(['opacAdmin', 'libraryAdmin'])){
			$location = new Location();
			if ($copyFromLocation->get($fromLocationId)){
				$facetsToCopy = $copyFromLocation->facets;
				foreach ($facetsToCopy as $facetKey => $facet){
					$facet->locationId       = $location->locationId;
					$facet->id               = null;
					$facetsToCopy[$facetKey] = $facet;
				}
				$location->facets                            = $facetsToCopy;
				$location->useLibraryCombinedResultsSettings = $copyFromLocation->useLibraryCombinedResultsSettings;
				$location->enableCombinedResults             = $copyFromLocation->enableCombinedResults;
				$location->defaultToCombinedResults          = $copyFromLocation->defaultToCombinedResults;
				$combinedResultsToCopy                       = $copyFromLocation->combinedResultSections;
				foreach ($combinedResultsToCopy as $key => $combinedResult){
					$combinedResult->locationId  = $location->locationId;
					$combinedResult->id          = null;
					$combinedResultsToCopy[$key] = $combinedResult;
				}
				$location->combinedResultSections = $copyFromLocation->combinedResultSections;
				$location->showStandardReviews    = $copyFromLocation->showStandardReviews;
				$location->showGoodReadsReviews   = $copyFromLocation->showGoodReadsReviews;
				$location->showFavorites          = $copyFromLocation->showFavorites;
				$fullRecordDisplayToCopy          = $copyFromLocation->moreDetailsOptions;
				foreach ($fullRecordDisplayToCopy as $key => $displayItem){
					$displayItem->locationId       = $location->locationId;
					$displayItem->id               = null;
					$fullRecordDisplayToCopy[$key] = $displayItem;
				}
				$location->moreDetailsOptions       = $fullRecordDisplayToCopy;
				$location->showEmailThis            = $copyFromLocation->showEmailThis;
				$location->showShareOnExternalSites = $copyFromLocation->showShareOnExternalSites;
				$location->showComments             = $copyFromLocation->showComments;
				$location->showQRCode               = $copyFromLocation->showQRCode;
				$location->showStaffView            = $copyFromLocation->showStaffView;
				$browseCategoriesToCopy             = $copyFromLocation->browseCategories;
				foreach ($browseCategoriesToCopy as $key => $category){
					$category->locationId         = $location->locationId;
					$category->id                 = null;
					$browseCategoriesToCopy[$key] = $category;
				}
				$location->browseCategories          = $browseCategoriesToCopy;
				$location->defaultBrowseMode         = $copyFromLocation->defaultBrowseMode;
				$location->browseCategoryRatingsMode = $copyFromLocation->browseCategoryRatingsMode;
				$location->enableOverdriveCollection = $copyFromLocation->enableOverdriveCollection;
				$location->includeOverDriveAdult     = $copyFromLocation->includeOverDriveAdult;
				$location->includeOverDriveTeen      = $copyFromLocation->includeOverDriveTeen;
				$location->includeOverDriveKids      = $copyFromLocation->includeOverDriveKids;

				$hooplaSettings = $copyFromLocation->hooplaSettings;
				foreach ($hooplaSettings as $key => $setting){
					$setting->locationId  = $location->locationId;
					$setting->id          = null;
					$hooplaSettings[$key] = $setting;
				}
				$location->hooplaSettings = $hooplaSettings;
				$copyFromLocation->getHours();
				$hoursToCopy = $copyFromLocation->hours;
				foreach ($hoursToCopy as $key => $hour){

					$hour->locationId  = $location->locationId;
					$hour->id          = null;
					$hoursToCopy[$key] = $hour;
				}
				$location->hours = $hoursToCopy;
				$includedToCopy  = $copyFromLocation->recordsToInclude;
				foreach ($includedToCopy as $key => $include){
					$include->locationId  = $location->locationId;
					$include->id          = null;
					$includedToCopy[$key] = $include;
				}
				$location->recordsToInclude = $includedToCopy;
				$recordsOwned               = $copyFromLocation->recordsOwned;
				foreach ($recordsOwned as $key => $owned){
					$owned->locationId  = $location->locationId;
					$owned->id          = null;
					$recordsOwned[$key] = $owned;
				}
				$location->recordsOwned = $recordsOwned;
				$location               = clone $copyFromLocation;
				$location->code         = $code;
				$location->displayName  = $name;


				if ($location->insert()){
					$editLink = "/Admin/Locations?objectAction=edit&id=" . $location->locationId;;
					$results['body']    = '<div class="alert alert-success">Location Cloned.</div><div>You may need to edit the following settings:<br /><ul><li>library</li><li>address</li><li>nearby location</li><li>valid pickup branch</li><li>free text fields in search facets</li><li>browse categories</li><li>Overdrive and Hoopla settings</li><li>hours</li><li>records owned</li></div>';
					$results['buttons'] = "<button class='btn btn-default' type= 'button' title='SaveReturn' onclick='location.href=\"/Admin/Locations\";'>Return to Location List</button><button class='btn btn-primary' type= 'button' title='SaveEdit' onclick='location.href=\"" . $editLink . "\";'>Edit New Location</button>";
				}

			}
		}

		return $results;
	}

	function cloneLibrary(){
		$results        = [
			'title' => 'Clone Location',
			'body'  => '<div class="alert alert-danger">Clone Failed</div>',
		];
		$fromLocationId = trim($_REQUEST['from']);
		$subdomain      = trim($_REQUEST['subdomain']);
		$name           = trim($_REQUEST['displayName']);
		$abName         = "";
		$facetLabel     = "";
		if ($_REQUEST['abName']){
			$abName = trim($_REQUEST['abName']);
		}
		if ($_REQUEST['facetLabel']){
			$facetLabel = $_REQUEST['facetLabel'];
		}
		$user = UserAccount::getLoggedInUser();
		if (UserAccount::userHasRole("opacAdmin")){
			$library  = new Library();
			$copyFrom = new Library();

			if ($copyFrom->get($fromLocationId)){
				$libraryId = null;

				$copyFromFacets = $copyFrom->facets;
				foreach ($copyFromFacets as $key => $facet){
					$facet->id            = null;
					$facet->libraryId     = $library->libraryId;
					$copyFromFacets[$key] = $facet;
				}
				$library->facets  = $copyFromFacets;
				$copyFromCombined = $copyFrom->combinedResultSections;
				foreach ($copyFromCombined as $key => $combined){
					$combined->id           = null;
					$combined->libraryId    = $library->libraryId;
					$copyFromCombined[$key] = $combined;
				}
				$library->combinedResultSections = $copyFromCombined;
				$copyFromMoreDetails             = $copyFrom->moreDetailsOptions;
				foreach ($copyFromMoreDetails as $key => $moreDetail){
					$moreDetail->id            = null;
					$moreDetail->libraryId     = $library->libraryId;
					$copyFromMoreDetails[$key] = $moreDetail;
				}
				$library->moreDetailsOptions = $copyFromMoreDetails;
				$copyFromBrowseCategories    = $copyFrom->browseCategories;
				foreach ($copyFromBrowseCategories as $key => $category){
					$category->id                   = null;
					$category->libraryId            = $library->libraryId;
					$copyFromBrowseCategories[$key] = $category;
				}
				$library->browseCategories     = $copyFromBrowseCategories;
				$copyFromMaterialRequestFields = $copyFrom->materialsRequestFieldsToDisplay;
				foreach ($copyFromMaterialRequestFields as $key => $requestField){
					$requestField->id                    = null;
					$requestField->libraryId             = $library->libraryId;
					$copyFromMaterialRequestFields[$key] = $requestField;
				}
				$library->materialsRequestFieldsToDisplay = $copyFromMaterialRequestFields;
				$copyFromMaterialRequestFormats           = $copyFrom->materialsRequestFormats;
				foreach ($copyFromMaterialRequestFormats as $key => $requestFormat){
					$requestFormat->id                    = null;
					$requestFormat->libraryId             = $library->libraryId;
					$copyFromMaterialRequestFormats[$key] = $requestFormat;
				}
				$library->materialRequestFormats   = $copyFromMaterialRequestFormats;
				$copyFromMaterialRequestFormFields = $copyFrom->materialsRequestFormFields;
				foreach ($copyFromMaterialRequestFormFields as $key => $requestFormField){
					$requestFormField->id                    = null;
					$requestFormField->libraryId             = $library->libraryId;
					$copyFromMaterialRequestFormFields[$key] = $requestFormField;
				}
				$library->materialsRequestFormFields = $copyFromMaterialRequestFormFields;
				$copyFromHooplaSettings              = $copyFrom->hooplaSettings;
				foreach ($copyFromHooplaSettings as $key => $hoopla){
					$hoopla->id                   = null;
					$hoopla->libraryId            = $library->libraryId;
					$copyFromHooplaSettings[$key] = $hoopla;
				}
				$library->hooplaSettings    = $copyFromHooplaSettings;
				$copyFromArchiveMoreDetails = $copyFrom->archiveMoreDetailsOptions;
				foreach ($copyFromArchiveMoreDetails as $key => $archiveDetail){
					$archiveDetail->id                = null;
					$archiveDetail->libraryId         = $library->libraryId;
					$copyFromArchiveMoreDetails[$key] = $archiveDetail;
				}
				$library->archiveMoreDetailsOptions = $copyFromArchiveMoreDetails;
				$copyFromExploreMoreBar             = $copyFrom->exploreMoreBar;
				foreach ($copyFromExploreMoreBar as $key => $explore){
					$explore->id                  = null;
					$explore->libraryId           = $library->libraryId;
					$copyFromExploreMoreBar[$key] = $explore;
				}
				$library->exploreMoreBar     = $copyFromExploreMoreBar;
				$copyFromArchiveSearchFacets = $copyFrom->archiveSearchFacets;
				foreach ($copyFromArchiveSearchFacets as $key => $archiveFacet){
					$archiveFacet->id                  = null;
					$archiveFacet->libraryId           = $library->libraryId;
					$copyFromArchiveSearchFacets[$key] = $archiveFacet;
				}
				$library->archiveSearchFacets = $copyFromArchiveSearchFacets;
				$copyFromHolidays             = $copyFrom->holidays;
				foreach ($copyFromHolidays as $key => $holiday){
					$holiday->id            = null;
					$holiday->libraryId     = $library->libraryId;
					$copyFromHolidays[$key] = $holiday;
				}
				$library->holidays    = $copyFromHolidays;
				$copyFromLibraryLinks = $copyFrom->libraryLinks;
				foreach ($copyFromLibraryLinks as $key => $libraryLink){
					$libraryLink->id            = null;
					$libraryLink->libraryId     = $library->libraryId;
					$copyFromLibraryLinks[$key] = $libraryLink;
				}
				$library->libraryLinks   = $copyFromLibraryLinks;
				$copyFromLibraryTopLinks = $copyFrom->libraryTopLinks;
				foreach ($copyFromLibraryTopLinks as $key => $topLink){
					$topLink->id                   = null;
					$topLink->libraryId            = $library->libraryId;
					$copyFromLibraryTopLinks[$key] = $topLink;
				}
				$library->libraryTopLinks = $copyFromLibraryTopLinks;
				$copyFromRecordsOwned     = $copyFrom->recordsOwned;
				foreach ($copyFromRecordsOwned as $key => $recordOwned){
					$recordOwned->id            = null;
					$recordOwned->libraryId     = $library->libraryId;
					$copyFromRecordsOwned[$key] = $recordOwned;
				}
				$library->recordsOwned    = $copyFromRecordsOwned;
				$copyFromRecordsToInclude = $copyFrom->recordsToInclude;
				foreach ($copyFromRecordsToInclude as $key => $includedRecord){
					$includedRecord->id             = null;
					$includedRecord->libraryId      = $library->libraryId;
					$copyFromRecordsToInclude[$key] = $includedRecord;
				}
				$library->recordsToInclude = $copyFromRecordsToInclude;

				$library                         = clone $copyFrom;
				$library->libraryId              = $libraryId;
				$library->displayName            = $name;
				$library->subdomain              = $subdomain;
				$library->abbreviatedDisplayName = $abName;
				$library->isDefault              = false;
				$library->facetLabel             = $facetLabel;

				if ($library->insert()){
					$editLink           = "/Admin/Libraries?objectAction=edit&id=" . $library->libraryId;
					$results['body']    = '<div class="alert alert-success">Library Cloned.</div><div>You may need to edit the following settings:<br /><ul><li>theme name</li><li>home link</li><li>contact links</li><li>ILS code</li><li>Sierra scope</li><li>p-types</li><li>self registration</li><li>free text fields in the search facets section</li><li>browse categories</li><li>materials request settings</li><li>Hoopla info</li><li>google analytics code</li><li>sidebar links</li><li>records owned</li><li>records to include</li></ul></div>';
					$results['buttons'] = "<button class='btn btn-default' type= 'button' title='SaveReturn' onclick='location.href=\"/Admin/Libraries\";'>Return to Library List</button><button class='btn btn-primary' type= 'button' title='SaveEdit' onclick='location.href=\"" . $editLink . "\";'>Edit New Library</button>";
				}

			}
		}
		return $results;
	}

	function fileExists(){
		$filename    = trim($_REQUEST['fileName']);
		$storagePath = trim($_REQUEST['storagePath']);

		if (file_exists($storagePath . DIRECTORY_SEPARATOR . "original" . DIRECTORY_SEPARATOR . $filename)){
			return ["exists" => "true"];
		}
		return ["exists" => "false"];
	}

	function loadPtypes(){
		$results = [
			'title' => 'Load Patron Types',
			'body'  => '<div class="alert alert-danger">Failed to load Patron Types</div>',
		];

		$sierraDna = new SierraDNA();
		$res       = $sierraDna->loadPtypes();
		if ($res){
			$results = [
				'title' => 'Load Patron Types',
				'body'  => '<div class="alert alert-success">Patron Types loaded.</div>',
			];
		}
		return $results;
	}

}
