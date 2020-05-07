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

require_once ROOT_DIR . '/AJAXHandler.php';

class Admin_AJAX extends AJAXHandler {

	protected $methodsThatRespondWithJSONUnstructured = array(
		'getAddToWidgetForm',
		'markProfileForRegrouping',
		'markProfileForReindexing',
		'copyHooplaSettingsFromLibrary',
		'copyHooplaSettingsFromLocation',
		'clearLocationHooplaSettings',
		'clearLibraryHooplaSettings',
        'displayCopyFromPrompt',
        'displayClonePrompt',
        'copyHourSettingsFromLocation',
        'copyBrowseCategoriesFromLocation',
        'copyIncludedRecordsFromLocation',
        'copyFullRecordDisplayFromLocation',
        'resetFacetsToDefault',
        'resetMoreDetailsToDefault',
        'copyFacetSettingsFromLocation',
        'cloneLocation',

	);

	function getAddToWidgetForm(){
		global $interface;
		$user = UserAccount::getLoggedInUser();
		// Display Page
		$interface->assign('id', strip_tags($_REQUEST['id']));
		$interface->assign('source', strip_tags($_REQUEST['source']));
		require_once ROOT_DIR . '/sys/Widgets/ListWidget.php';
		$listWidget      = new ListWidget();
		if (UserAccount::userHasRoleFromList(['libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager'])){
			//Get all widgets for the library
			$userLibrary           = UserAccount::getUserHomeLibrary();
			$listWidget->libraryId = $userLibrary->libraryId;
		}
		$listWidget->orderBy('name');
		$existingWidgets = $listWidget->fetchAll('id', 'name');
		$interface->assign('existingWidgets', $existingWidgets);
		$results = array(
			'title'        => 'Create a Widget',
			'modalBody'    => $interface->fetch('Admin/addToWidgetForm.tpl'),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='$(\"#bulkAddToList\").submit();'>Create Widget</button>",
		);
		return $results;
	}
    function copyHoursFromLocation(){

    }

    /**
     * Ajax class which calls copyLibraryHooplaSettings in order to copy the parent library's hoopla settings
     *
     * @return false|string if no value is returned a value of false will be returned
     */
    function copyHooplaSettingsFromLibrary(){
		$results = array(
			'title'     => 'Copy Library Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
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
	    $results = array(
	        'title' =>'Copy Location Hoopla Settings',
            'body'  =>'<div class="alert alert-danger">There was an error.</div>',
        );

	    $user = UserAccount::getLoggedInUser();
	    if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])){
	        $locationId = trim($_REQUEST['id']);
	        $locationFromId = trim($_REQUEST['fromId']);
	        if(ctype_digit($locationId) && ctype_digit($locationFromId)){
	            $location = new Location();
	            if($location->get($locationId)){
	                $location->clearHooplaSettings();
	                if($location->copyLocationHooplaSettings($locationFromId)){
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
		$results = array(
			'title'     => 'Clear Location Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
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
		$results = array(
			'title'     => 'Clear Library Hoopla Settings',
			'body' => '<div class="alert alert-danger">There was an error.</div>',
		);

		$user    = UserAccount::getLoggedInUser();
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
        $results = array(
            'title' => 'Clone Location',
            'body' => 'No Data available',
        );
        $user = UserAccount::getLoggedInUser();
        if(UserAccount::userHasRoleFromList(['opacAdmin'])){
            $command = trim($_REQUEST['command']);
            $location = new Location();
            $locationId = $user->getHomeLibrary()->getLocationIdsForLibrary()[0];
            if($location->get($locationId))
            {
                $allLocations = $this->getLocationList($locationId);
                $options = " ";

                foreach($allLocations as $findKey =>$findLocation)
                {
                    $options .= "<option value='" . $findKey . "'>" . $findLocation->displayName . "</option>";
                }
                $results['body'] = "<label for='code'>New Location Code:</label> <input type='text' class='form-control' id='LocCode' name='LocCode'/><label for='name'>Display Name</label> <input type='text' class='form-control' id='name' name='name' /><label for='fromId'>Clone From</label> <select id= 'fromId' name='fromId' class='form-control'>" . $options . "</select>";
                $results['buttons'] = "<button class='btn btn-primary' type= 'button' title='Copy' onclick='return Pika.Admin." . $command ."(document.querySelector(\"#fromId\").value, document.querySelector(\"#name\").value, document.querySelector(\"#LocCode\").value);'>Clone</button>";

            }
        }
        return $results;
    }
    /**
     * Displays list of library locations to the user in order to select the location to copy from
     * @return string[] select box with buttons to choose copy location
     */
    function displayCopyFromPrompt(){
	    $results = array(
            'title' => 'Select Location to Copy From',
            'body' => 'Copy was unsuccessful',

        );
	    $user   = UserAccount::getLoggedInUser();
	    if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            $locationId = trim($_REQUEST['id']);
            $command = trim($_REQUEST['command']);
            if(ctype_digit($locationId)) {
                $location = new Location();
                if($location->get($locationId)) {

                    $allLocations = $this->getLocationList($locationId);

                    unset($allLocations[$locationId]);
                    $options = " ";
                    foreach($allLocations as $findKey =>$findLocation)
                    {
                        $options .= "<option value='" . $findKey . "'>" . $findLocation->displayName . "</option>";
                    }


                    $results['body'] = "<select id= 'fromId' name='fromId' class='form-control'>" . $options . "</select>";
                    $results['buttons'] = "<button class='btn btn-primary' type= 'button' title='Copy' onclick='return Pika.Admin." . $command ."(" . $locationId . ", document.querySelector(\"#fromId\").value);'>Copy</button>";
                }
            }
        }
	    return $results;;
    }

    /**
     * Gets locations available to user to copy from for the logged in administrative user
     *
     * @param $locationId id of homeLocation
     * @return array of available locations
     */
    function getLocationList($locationId)
    {
        //Look lookup information for display in the user interface
        $user = UserAccount::getLoggedInUser();
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
        $copyTo = new Location();
        $copyTo->libraryId = $copyToLibrary;
        $copyTo->find();

        $location->find();

        $locationList = array();

        while ($copyTo->fetch()){

            $locationList[$copyTo->locationId] = clone $copyTo;

        }
        while ($location->fetch()){

            $locationList[$location->locationId] = clone $location;
        }



        return $locationList;
    }
    /**
     * Ajax method copies hours between library locations
     *
     * @return string[] array containing the title and body displayed in the popup
     */
    function copyHourSettingsFromLocation()
    {
        $results = array(
            'title' => 'Copy Hours From Location',
            'body' => '<div class="alert alert-danger">Copy was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $fromLocationId = trim($_REQUEST['fromId']);
        $location = new Location();
        $copyFromLocation = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin']))
        {
            if($location->get($locationId) && $copyFromLocation->get($fromLocationId))
            {

                $copyFromLocation->getHours();
                $hoursToCopy = $copyFromLocation->hours;
                foreach ($hoursToCopy as $key => $hour) {

                    $hour->locationId = $locationId;
                    $hour->id = null;
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
    function copyBrowseCategoriesFromLocation()
    {
        $results = array(
            'title' => 'Copy Browse Categories From Location',
            'body' => '<div class="alert alert-danger">Copy was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $fromLocationId = trim($_REQUEST['fromId']);
        $location = new Location();
        $copyFromLocation = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId) && $copyFromLocation->get($fromLocationId))
            {
                $location->clearBrowseCategories();

                $browseCategoriesToCopy = $copyFromLocation->browseCategories;
                foreach ($browseCategoriesToCopy as $key => $category) {
                    $category->locationId = $locationId;
                    $category->id = null;
                    $browseCategoriesToCopy[$key] = $category;
                }
                $location->browseCategories = $browseCategoriesToCopy;
                $location->defaultBrowseMode = $copyFromLocation->defaultBrowseMode;
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
    function copyFacetSettingsFromLocation()
    {
        $results = array(
            'title' => 'Copy Facets From Location',
            'body' => '<div class="alert alert-danger">Copy was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $fromLocationId = trim($_REQUEST['fromId']);
        $location = new Location();
        $copyFromLocation = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId) && $copyFromLocation->get($fromLocationId))
            {
                $location->clearFacets();

                $facetsToCopy = $copyFromLocation->facets;
                foreach ($facetsToCopy as $facetKey => $facet){
                    $facet->locationId       = $locationId;
                    $facet->id               = null;
                    $facetsToCopy[$facetKey] = $facet;
                }
                $location->baseAvailabilityToggleOnLocalHoldingsOnly    = $copyFromLocation->baseAvailabilityToggleOnLocalHoldingsOnly;
                $location->includeOnlineMaterialsInAvailableToggle      = $copyFromLocation->includeOnlineMaterialsInAvailableToggle;
                $location->includeAllLibraryBranchesInFacets            = $copyFromLocation->includeAllLibraryBranchesInFacets;
                $location->additionalLocationsToShowAvailabilityFor     = $copyFromLocation->additionalLocationsToShowAvailabilityFor;
                $location->includeAllRecordsInShelvingFacets            = $copyFromLocation->includeAllRecordsInShelvingFacets;
                $location->includeAllRecordsInDateAddedFacets           = $copyFromLocation->includeAllRecordsInDateAddedFacets;
                $location->includeOnOrderRecordsInDateAddedFacetValues  = $copyFromLocation->includeOnOrderRecordsInDateAddedFacetValues;
                $location->facets = $facetsToCopy;
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
    function copyIncludedRecordsFromLocation()
    {
        $results = array(
            'title' => 'Copy Included Records From Location',
            'body' => '<div class="alert alert-danger">Copy was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $fromLocationId = trim($_REQUEST['fromId']);
        $location = new Location();
        $copyFromLocation = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId) && $copyFromLocation->get($fromLocationId))
            {

                $location->clearLocationRecordsToInclude();

                $includedToCopy = $copyFromLocation->recordsToInclude;
                foreach($includedToCopy as $key=>$include)
                {
                    $include->locationId    = $locationId;
                    $include->id            = null;
                    $includedToCopy[$key]   = $include;
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
    function copyFullRecordDisplayFromLocation()
    {

        $results = array(
            'title' => 'Copy Full Record Display From Location',
            'body' => '<div class="alert alert-danger">Copy was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $fromLocationId = trim($_REQUEST['fromId']);
        $location = new Location();
        $copyFromLocation = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId) && $copyFromLocation->get($fromLocationId))
            {
                $location->clearMoreDetailsOptions();
                $fullRecordDisplayToCopy = $copyFromLocation->moreDetailsOptions;
                foreach ($fullRecordDisplayToCopy as $key=>$displayItem)
                {
                    $displayItem->locationId        = $locationId;
                    $displayItem->id                = null;
                    $fullRecordDisplayToCopy[$key]  = $displayItem;
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
    function resetFacetsToDefault()
    {
        $results = array(
            'title' => 'Reset Facets To Default',
            'body' => '<div class="alert alert-danger">Reset was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $location = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId))
            {
                $location->clearFacets();

                $defaultFacets = Location::getDefaultFacets($locationId);

                $location->facets = $defaultFacets;
                $location->update();
                $results['body'] = '<div class="alert alert-success">Facets Reset to Default.</div>';
            }
        }

        return $results;
    }
    function resetMoreDetailsToDefault()
    {
        $results = array(
            'title' => 'Reset Facets To Default',
            'body' => '<div class="alert alert-danger">Reset was unsuccessful</div>',
        );
        $user = UserAccount::getLoggedInUser();
        $locationId = trim($_REQUEST['id']);
        $location = new Location();
        if(UserAccount::userHasRoleFromList(['opacAdmin','libraryAdmin'])) {
            if ($location->get($locationId))
            {
                $location->clearMoreDetailsOptions();

                $defaultOptions = array();
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

    function cloneLocation()
    {
        $results = array(
            'title' => 'Clone Location',
            'body' => '<div class="alert alert-danger">Clone Failed</div>',
        );
        $fromLocationId = trim($_REQUEST['from']);
        $code = trim($_REQUEST['code']);
        $name = trim($_REQUEST['name']);
        $copyFromLocation = new Location();
        $user = UserAccount::getLoggedInUser();
        if(UserAccount::userHasRole("opacAdmin")) {
            $location = new Location();
             if($copyFromLocation->get($fromLocationId)) {
                 $location->code = $code;
                 $location->displayName = $name;
                 $location->showDisplayNameInHeader = $copyFromLocation->showDisplayNameInHeader;
                 $location->libraryId = $copyFromLocation->libraryId;
                 $location->showInLocationsAndHoursList = $copyFromLocation->showInLocationsAndHoursList;
                 $location->address = $copyFromLocation->address;
                 $location->phone = $copyFromLocation->phone;
                 $location->nearbyLocation1 = $copyFromLocation->nearbyLocation1;
                 $location->nearbyLocation2 = $copyFromLocation->nearbyLocation2;
                 $location->automaticTimeoutLength = $copyFromLocation->automaticTimeoutLength;
                 $location->automaticTimeoutLengthLoggedOut = $copyFromLocation->automaticTimeoutLengthLoggedOut;
                 $location->homeLink = $copyFromLocation->homeLink;
                 $location->additionalCss = $copyFromLocation->additionalCss;
                 $location->headerText = $copyFromLocation->headerText;
                 $location->scope = $copyFromLocation->scope;
                 $location->useScope = $copyFromLocation->useScope;
                 $location->defaultPType = $copyFromLocation->defaultPType;
                 $location->validHoldPickupBranch = $copyFromLocation->validHoldPickupBranch;
                 $location->showHoldButton = $copyFromLocation->showHoldButton;
                 $location->ptypesToAllowRenewals = $copyFromLocation->ptypesToAllowRenewals;
                 $location->restrictSearchByLocation = $copyFromLocation->restrictSearchByLocation;
                 $location->publicListsToInclude = $copyFromLocation->publicListsToInclude;
                 $location->boostByLocation = $copyFromLocation->boostByLocation;
                 $location->additionalLocalBoostFactor = $copyFromLocation->additionalLocalBoostFactor;
                 $location->systemsToRepeatIn = $copyFromLocation->systemsToRepeatIn;
                 $location->repeatSearchOption = $copyFromLocation->repeatSearchOption;
                 $location->repeatInOnlineCollection = $copyFromLocation->repeatInOnlineCollection;
                 $location->repeatInProspector = $copyFromLocation->repeatInProspector;
                 $location->repeatInWorldCat = $copyFromLocation->repeatInWorldCat;
                 $location->repeatInOverdrive = $copyFromLocation->repeatInOverdrive;
                 $location->availabilityToggleLabelSuperScope = $copyFromLocation->availabilityToggleLabelSuperScope;
                 $location->availabilityToggleLabelLocal = $copyFromLocation->availabilityToggleLabelLocal;
                 $location->availabilityToggleLabelAvailable = $copyFromLocation->availabilityToggleLabelAvailable;
                 $location->availabilityToggleLabelAvailableOnline = $copyFromLocation->availabilityToggleLabelAvailableOnline;
                 $location->baseAvailabilityToggleOnLocalHoldingsOnly = $copyFromLocation->baseAvailabilityToggleOnLocalHoldingsOnly;
                 $location->includeOnlineMaterialsInAvailableToggle = $copyFromLocation->includeOnlineMaterialsInAvailableToggle;
                 $location->facetLabel = $copyFromLocation->facetLabel;
                 $location->includeAllLibraryBranchesInFacets = $copyFromLocation->includeAllLibraryBranchesInFacets;
                 $location->includeAllRecordsInDateAddedFacets = $copyFromLocation->includeAllRecordsInDateAddedFacets;
                 $location->includeAllRecordsInShelvingFacets = $copyFromLocation->includeAllRecordsInShelvingFacets;
                 $location->additionalLocationsToShowAvailabilityFor = $copyFromLocation->additionalLocationsToShowAvailabilityFor;
                 $facetsToCopy = $copyFromLocation->facets;
                 foreach ($facetsToCopy as $facetKey => $facet) {
                     $facet->locationId = $location->locationId;
                     $facet->id = null;
                     $facetsToCopy[$facetKey] = $facet;
                 }
                 $location->facets = $facetsToCopy;
                 $location->useLibraryCombinedResultsSettings = $copyFromLocation->useLibraryCombinedResultsSettings;
                 $location->enableCombinedResults = $copyFromLocation->enableCombinedResults;
                 $location->defaultToCombinedResults = $copyFromLocation->defaultToCombinedResults;
                 $combinedResultsToCopy = $copyFromLocation->combinedResultSections;
                 foreach ($combinedResultsToCopy as $key => $combinedResult) {
                     $combinedResult->locationId = $location->locationId;
                     $combinedResult->id = null;
                     $combinedResultsToCopy[$key] = $combinedResult;
                 }
                 $location->combinedResultSections = $copyFromLocation->combinedResultSections;
                 $location->showStandardReviews = $copyFromLocation->showStandardReviews;
                 $location->showGoodReadsReviews = $copyFromLocation->showGoodReadsReviews;
                 $location->showFavorites = $copyFromLocation->showFavorites;
                 $fullRecordDisplayToCopy = $copyFromLocation->moreDetailsOptions;
                 foreach ($fullRecordDisplayToCopy as $key => $displayItem) {
                     $displayItem->locationId = $location->locationId;
                     $displayItem->id = null;
                     $fullRecordDisplayToCopy[$key] = $displayItem;
                 }
                 $location->moreDetailsOptions = $fullRecordDisplayToCopy;
                 $location->showEmailThis = $copyFromLocation->showEmailThis;
                 $location->showShareOnExternalSites = $copyFromLocation->showShareOnExternalSites;
                 $location->showComments = $copyFromLocation->showComments;
                 $location->showQRCode = $copyFromLocation->showQRCode;
                 $location->showStaffView = $copyFromLocation->showStaffView;
                 $browseCategoriesToCopy = $copyFromLocation->browseCategories;
                 foreach ($browseCategoriesToCopy as $key => $category) {
                     $category->locationId = $location->locationId;
                     $category->id = null;
                     $browseCategoriesToCopy[$key] = $category;
                 }
                 $location->browseCategories = $browseCategoriesToCopy;
                 $location->defaultBrowseMode = $copyFromLocation->defaultBrowseMode;
                 $location->browseCategoryRatingsMode = $copyFromLocation->browseCategoryRatingsMode;
                 $location->enableOverdriveCollection = $copyFromLocation->enableOverdriveCollection;
                 $location->includeOverDriveAdult = $copyFromLocation->includeOverDriveAdult;
                 $location->includeOverDriveTeen = $copyFromLocation->includeOverDriveTeen;
                 $location->includeOverDriveKids = $copyFromLocation->includeOverDriveKids;

                 $hooplaSettings = $copyFromLocation->hooplaSettings;
                 foreach ($hooplaSettings as $key => $setting) {
                     $setting->locationId = $location->locationId;
                     $setting->id = null;
                     $hooplaSettings[$key] = $setting;
                 }
                 $location->hooplaSettings = $hooplaSettings;
                 $copyFromLocation->getHours();
                 $hoursToCopy = $copyFromLocation->hours;
                 foreach ($hoursToCopy as $key => $hour) {

                     $hour->locationId = $location->locationId;
                     $hour->id = null;
                     $hoursToCopy[$key] = $hour;
                 }
                 $location->hours = $hoursToCopy;
                 $includedToCopy = $copyFromLocation->recordsToInclude;
                 foreach ($includedToCopy as $key => $include) {
                     $include->locationId = $location->locationId;
                     $include->id = null;
                     $includedToCopy[$key] = $include;
                 }
                 $location->recordsToInclude = $includedToCopy;
                 $recordsOwned = $copyFromLocation->recordsOwned;
                 foreach ($recordsOwned as $key => $owned) {
                     $owned->locationId = $location->locationId;
                     $owned->id = null;
                     $recordsOwned[$key] = $owned;
                 }
                 $location->recordsToInclude = $includedToCopy;

                 if ($location->insert())
                 {
                     $results['body'] = '<div class="alert alert-success">Location Cloned.</div>';
                 }
                 
             }
        }

        return $results;
    }
	//	function markProfileForRegrouping(){
//		$result = array(
//			'success' => false,
//			'message' => 'Invalid Action',
//		);
//		$user = UserAccount::getLoggedInUser();
//		if (UserAccount::userHasRole('opacAdmin')){
//			$id = $_REQUEST['id'];
//			if (!empty($id) && ctype_digit($id)){
//				$indexProfile = new IndexingProfile();
//				if ($indexProfile->get($id)){
//					$result = $indexProfile->markProfileForRegrouping();
//				}
//			}
//		}
//		return json_encode($result);
//	}
//
//	function markProfileForReindexing(){
//		$result = array(
//			'success' => false,
//			'message' => 'Invalid Action',
//		);
//		$user = UserAccount::getLoggedInUser();
//		if (UserAccount::userHasRole('opacAdmin')){
//			$id = $_REQUEST['id'];
//			if (!empty($id) && ctype_digit($id)){
//				$indexProfile = new IndexingProfile();
//				if ($indexProfile->get($id)){
//					$result = $indexProfile->markProfileForReindexing();
//				}
//			}
//		}
//		return json_encode($result);
//	}
}
