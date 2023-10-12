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

class SearchSources {
	static function getSearchSources(){
		return self::getSearchSourcesDefault();
	}

	/**
	 * Handle breaking a string setting into an array of options where the options are delimited by a pipe.
	 *
	 * @param String $setting  String of option(s) separated by the pipe | character
	 * @return String[]       Array of valid options from the string
	 */
	private static function getOptionsFromSetting($setting): array{
		$options = [];
		if (!empty($setting)){
			foreach (explode('|', $setting) as $option){
				if (!empty($option)){ // prevent adding empty options
					$options[] = $option;
				}
			}
		}
		return $options;
	}

	private static function getSearchSourcesDefault(){
		$searchOptions = [];
		//Check to see if marmot catalog is a valid option
		global $library;
		global $configArray;
		$repeatSearchSetting               = '';
		$repeatInWorldCat                  = false;
		$repeatInProspector                = true;
		$repeatInOverdrive                 = false;
		$repeatInAlternateOverdriveLibrary = [];
		$systemsToRepeatIn                 = [];
		$searchGenealogy                   = true;
		$repeatCourseReserves              = false;
		$searchArchive                     = false;
		$searchEbsco                       = false;

		/** @var $locationSingleton Location */
		global $locationSingleton;
		$location = $locationSingleton->getActiveLocation();
		if (!empty($location->repeatInAlternateOverdriveLibrary)){
			$repeatInAlternateOverdriveLibrary = self::getOptionsFromSetting($location->repeatInAlternateOverdriveLibrary);
		}

		if (!empty($location->restrictSearchByLocation)){
			$repeatSearchSetting = $location->repeatSearchOption;
			$repeatInWorldCat    = $location->repeatInWorldCat == 1;
			$repeatInProspector  = $location->repeatInProspector == 1;
			$repeatInOverdrive   = $location->repeatInOverdrive == 1;
			if (strlen($location->systemsToRepeatIn) > 0){
				$systemsToRepeatIn = self::getOptionsFromSetting($location->systemsToRepeatIn);
			}else{
				$systemsToRepeatIn = self::getOptionsFromSetting($library->systemsToRepeatIn);
			}
		}elseif (isset($library)){
			$repeatSearchSetting = $library->repeatSearchOption;
			$repeatInWorldCat    = $library->repeatInWorldCat == 1;
			$repeatInProspector  = $library->repeatInProspector == 1;
			$repeatInOverdrive   = $library->repeatInOverdrive == 1;
			if (!empty($library->repeatInAlternateOverdriveLibrary)){
				$repeatInAlternateOverdriveLibrary = self::getOptionsFromSetting($library->repeatInAlternateOverdriveLibrary);
			}

			$systemsToRepeatIn = self::getOptionsFromSetting($library->systemsToRepeatIn);
		}
		if (isset($library)){
			$searchGenealogy      = $library->enableGenealogy;
			$repeatCourseReserves = $library->enableCourseReserves == 1;
			$searchArchive        = $library->enableArchive == 1;
			//TODO: Re-enable once we do full EDS integration
			//$searchEbsco = $library->edsApiProfile != '';
		}

		[$enableCombinedResults, $showCombinedResultsFirst, $combinedResultsName] = self::getCombinedSearchSetupParameters($location, $library);

		$marmotAdded = false;
		if ($enableCombinedResults && $showCombinedResultsFirst){
			$searchOptions['combinedResults'] = [
				'name'        => $combinedResultsName,
				'description' => "Combined results from multiple sources.",
				'catalogType' => 'combined'
			];
		}

		//Local search
		if (!empty($location) && $location->restrictSearchByLocation){
			$searchOptions['local'] = [
				'name'        => $location->displayName,
				'description' => "The {$location->displayName} catalog.",
				'catalogType' => 'catalog'
			];
		}elseif (isset($library)){
			$searchOptions['local'] = [
				'name'        => strlen($library->abbreviatedDisplayName) > 0 ? $library->abbreviatedDisplayName : $library->displayName,
				'description' => "The {$library->displayName} catalog.",
				'catalogType' => 'catalog'
			];
		}else{
			$marmotAdded            = true;
			$consortiumName         = $configArray['Site']['libraryName'];
			$searchOptions['local'] = [
				'name'        => "Entire $consortiumName Catalog",
				'description' => "The entire $consortiumName catalog.",
				'catalogType' => 'catalog'
			];
		}

		if ($location != null && $location->restrictSearchByLocation &&
					($repeatSearchSetting == 'marmot' || $repeatSearchSetting == 'librarySystem')
		){
			$searchOptions[$library->subdomain] = [
				'name'        => $library->displayName,
				'description' => "The entire {$library->displayName} catalog not limited to a particular branch.",
				'catalogType' => 'catalog'
			];
		}

		//Process additional systems to repeat in
		if (count($systemsToRepeatIn) > 0){
			foreach ($systemsToRepeatIn as $system){
				if (!empty($system)){
					$repeatInLibrary            = new Library();
					$repeatInLibrary->subdomain = $system;
					if ($repeatInLibrary->find(true)){
						$searchOptions[$repeatInLibrary->subdomain] = [
							'name'        => $repeatInLibrary->displayName,
							'description' => '',
							'catalogType' => 'catalog'
						];
					}else{
						//See if this is a repeat within a location
						$repeatInLocation       = new Location();
						$repeatInLocation->code = $system;
						if ($repeatInLocation->find(true)){
							$searchOptions[$repeatInLocation->code] = [
								'name'        => $repeatInLocation->displayName,
								'description' => '',
								'catalogType' => 'catalog'
							];
						}
					}
				}
			}
		}

		$includeOnlineOption = true;
		if ($location != null && $location->repeatInOnlineCollection == 0){
			$includeOnlineOption = false;
		}elseif ($library != null && $library->repeatInOnlineCollection == 0){
			$includeOnlineOption = false;
		}

		if ($includeOnlineOption){
			//eContent Search
			$searchOptions['econtent'] = [
				'name'        => 'Online Collection',
				'description' => 'Digital Media available for use online and with portable devices',
				'catalogType' => 'catalog'
			];
		}

		//Marmot Global search
		if (isset($library) &&
			($repeatSearchSetting == 'marmot') &&
			$library->restrictSearchByLibrary
			&& $marmotAdded == false
		){
			$consortiumName          = $configArray['Site']['libraryName'];
			$searchOptions['marmot'] = [
				'name'        => "$consortiumName Catalog",
				'description' => 'A consortium of libraries who share resources with your library.',
				'catalogType' => 'catalog'
			];
		}

		if ($searchEbsco){
			$searchOptions['ebsco'] = [
				'name'        => 'EBSCO',
				'description' => 'EBSCO',
				'catalogType' => 'ebsco'
			];
		}

		if ($searchArchive){
			$searchOptions['islandora'] = [
				'name'        => 'Local Digital Archive',
				'description' => 'Local Digital Archive in Colorado',
				'catalogType' => 'islandora'
			];
		}

		//Genealogy Search
		if ($searchGenealogy){
			$searchOptions['genealogy'] = [
				'name'        => 'Genealogy Records',
				'description' => 'Genealogy Records from Colorado',
				'catalogType' => 'genealogy'
			];
		}

		if ($enableCombinedResults && !$showCombinedResultsFirst){
			$searchOptions['combinedResults'] = [
				'name'        => $combinedResultsName,
				'description' => "Combined results from multiple sources.",
				'catalogType' => 'combined'
			];
		}

		//Overdrive
		if ($repeatInOverdrive){
			$searchOptions['overdrive'] = [
				'name'        => 'OverDrive Digital Catalog',
				'description' => 'Downloadable Books, Videos, Music, and eBooks with free use for library card holders.',
				'external'    => true,
				'catalogType' => 'catalog'
			];
		}
		foreach ($repeatInAlternateOverdriveLibrary as $alternateOverDriveLibrary){
			$searchOptions["overdrive$alternateOverDriveLibrary"] = [
				'name'        => 'OverDrive ' . ucfirst($alternateOverDriveLibrary) . ' Library',
				'description' => 'Downloadable Books, Videos, Music, and eBooks with free use for library card holders.',
				'external'    => true,
				'catalogType' => 'catalog'
			];
		}

		if ($repeatInProspector){
			$innReachEncoreName          = $configArray['InterLibraryLoan']['innReachEncoreName'];
			$searchOptions['prospector'] = [
				'name'        => $innReachEncoreName . ' Catalog',
				'description' => $innReachEncoreName == 'Prospector' ? 'A shared catalog of academic, public, and special libraries all over Colorado.' : 'A shared catalog for inter-library loaning.',
				'external'    => true,
				'catalogType' => 'catalog'
			];
		}

		//Course reserves for colleges
		if ($repeatCourseReserves){
			//Mesa State
			$searchOptions['course-reserves-course-name'] = [
				'name'        => 'Course Reserves by Name or Number',
				'description' => 'Search course reserves by course name or number',
				'external'    => true,
				'catalogType' => 'courseReserves'
			];
			$searchOptions['course-reserves-instructor']  = [
				'name'        => 'Course Reserves by Instructor',
				'description' => 'Search course reserves by professor, lecturer, or instructor name',
				'external'    => true,
				'catalogType' => 'courseReserves'
			];
		}

		if ($repeatInWorldCat){
			$searchOptions['worldcat'] = [
				'name'        => 'WorldCat',
				'description' => 'A shared catalog of libraries all over the world.',
				'external'    => true,
				'catalogType' => 'catalog'
			];
		}

		return $searchOptions;
	}

	/**
	 * @param $location
	 * @param $library
	 * @return array
	 */
	static function getCombinedSearchSetupParameters($location, $library){
		$enableCombinedResults    = false;
		$showCombinedResultsFirst = false;
		$combinedResultsName      = 'Combined Results';
		if ($location && !$location->useLibraryCombinedResultsSettings){
			$enableCombinedResults    = $location->enableCombinedResults;
			$showCombinedResultsFirst = $location->defaultToCombinedResults;
			$combinedResultsName      = $location->combinedResultsLabel;
			return [$enableCombinedResults, $showCombinedResultsFirst, $combinedResultsName];
		}else{
			if ($library){
				$enableCombinedResults    = $library->enableCombinedResults;
				$showCombinedResultsFirst = $library->defaultToCombinedResults;
				$combinedResultsName      = $library->combinedResultsLabel;
				return [$enableCombinedResults, $showCombinedResultsFirst, $combinedResultsName];
			}
		}
		return [$enableCombinedResults, $showCombinedResultsFirst, $combinedResultsName];
	}

	static function getWorldCatSearchType($type){
		switch ($type){
			case 'Subject':
				return 'su';
				break;
			case 'Author':
				return 'au';
				break;
			case 'Title':
				return 'ti';
				break;
			case 'ISN':
				return 'bn';
				break;
			case 'Keyword':
			default:
				return 'kw';
				break;
		}
	}



	public static function getExternalLink($searchSource, $type, $lookFor){
		/** @var Library $library */
		global $library;
		global $configArray;
		switch ($searchSource){
			case 'worldcat':
				$worldCatSearchType = self::getWorldCatSearchType($type);
				$worldCatLink       = "http://www.worldcat.org/search?q={$worldCatSearchType}%3A" . urlencode($lookFor);
				if (!empty($library->worldCatUrl)){
					$worldCatLink = $library->worldCatUrl;
					if (strpos($worldCatLink, '?') == false){
						$worldCatLink .= "?";
					}
					$worldCatLink .= "q={$worldCatSearchType}:" . urlencode($lookFor);
					//Repeat the search term with a parameter of queryString since some interfaces use that parameter instead of q
					$worldCatLink .= "&queryString={$worldCatSearchType}:" . urlencode($lookFor);
					if (strlen($library->worldCatQt) > 0){
						$worldCatLink .= "&qt=" . $library->worldCatQt;
					}
				}
				return $worldCatLink;
			case 'overdrive':
				$overDriveUrl = $configArray['OverDrive']['url'];
				return "$overDriveUrl/search?query=" . urlencode($lookFor);
//			case 'overdriveKids':
//				$overDriveUrl = $configArray['OverDrive']['url'];
//				return "$overDriveUrl/library/kids/search?query=" . urlencode($lookFor);
			case 'prospector':
				$prospectorSearchType = self::getProspectorSearchType($type);
				$lookFor              = str_replace('+', '%20', rawurlencode($lookFor));
				// Handle special exception: ? character in the search must be encoded specially
				$lookFor = str_replace('%3F', 'Pw%3D%3D', $lookFor);
				if ($prospectorSearchType != ' '){
					$lookFor = "$prospectorSearchType:(" . $lookFor . ")";
				}
				$innReachEncoreHostUrl = $configArray['InterLibraryLoan']['innReachEncoreHostUrl'];

				return $innReachEncoreHostUrl . '/iii/encore/search/C|S' . $lookFor . '|Orightresult|U1?lang=eng&amp;suite=def';
			case 'amazon':
				return "http://www.amazon.com/s/ref=nb_sb_noss?url=search-alias%3Daps&field-keywords=" . urlencode($lookFor);
			case 'course-reserves-course-name':
				$catalogConnection  = CatalogFactory::getCatalogConnectionInstance(); // TODO: this would need handling when there are multiple Account Profiles
				$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
				return "$classicOpacBaseURL/search~S{$library->scope}/r?SEARCH=" . urlencode($lookFor);
			case 'course-reserves-instructor':
				$catalogConnection  = CatalogFactory::getCatalogConnectionInstance(); // TODO: this would need handling when there are multiple Account Profiles
				$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
				return "$classicOpacBaseURL/search~S{$library->scope}/p?SEARCH=" . urlencode($lookFor);
			default:
				if (strpos($searchSource, 'overdrive') === 0){
					$overDriveUrl              = $configArray['OverDrive']['url'];
					$alternateOverDriveLibrary = str_replace('overdrive', '', $searchSource);
					return "$overDriveUrl/library/$alternateOverDriveLibrary/search?query=" . urlencode($lookFor);
				} else{
					return '';
				}
		}
	}

	static function getProspectorSearchType($type){
		switch ($type){
			case 'Subject':
				return 'd';
				break;
			case 'Author':
				return 'a';
				break;
			case 'Title':
				return 't';
				break;
			case 'ISN':
				return 'i';
				break;
			case 'Keyword':
				return ' ';
				break;
		}
		return ' ';
	}
}
