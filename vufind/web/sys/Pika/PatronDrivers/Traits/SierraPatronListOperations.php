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
 * @author: Pascal Brammeier
 * Date: 1/11/2020
 *
 */


namespace Pika;

use Curl\Curl;

require_once ROOT_DIR . "/sys/Pika/PatronDrivers/Traits/PatronListOperations.php";


trait SierraPatronListOperations {

	use PatronListOperations;


 public $classicListsRegex = '/<tr[^>]*?class="patFuncEntry"[^>]*?>.*?<input type="checkbox" id ="(\\d+)".*?<a.*?>(.*?)<\/a>.*?<td[^>]*class="patFuncDetails">(.*?)<\/td>.*?<\/tr>/si';

	/**
	 * Import Lists from the ILS
	 *
	 * @param  \User $patron
	 * @return array - an array of results including the names of the lists that were imported as well as number of titles.
	 */
	function importListsFromIls(\User $patron){
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		$results = ['success' => false];
		$errors  = [];

		//Get the page which contains a table with all lists in them.
		$classicListsPage = $this->_curlLegacy($patron, 'mylists');
		if ($classicListsPage){
			//Get the actual table
			if (preg_match('/<table[^>]*?class="patFunc"[^>]*?>(.*?)<\/table>/si', $classicListsPage, $listsPageMatches)){
				$allListTable = $listsPageMatches[1];

				$results = [
					'success'     => true,
					'totalTitles' => 0,
					'totalLists'  => 0
				];

				require_once ROOT_DIR . '/sys/Grouping/GroupedWorkPrimaryIdentifier.php';
				require_once ROOT_DIR . '/sys/Grouping/GroupedWork.php';

				// Now that we have the table, get the actual list names and ids
				// (You can set $this->classicListsRegex in the Class handler for the specific library to handle differences for that library)
				preg_match_all($this->classicListsRegex, $allListTable, $listDetails, PREG_SET_ORDER);
				if (!empty($listDetails)){
					foreach ($listDetails as $listDetail){
						$classicListId          = $listDetail[1];
						$classicListTitle       = html_entity_decode(strip_tags($listDetail[2]));
						$classicListDescription = html_entity_decode(strip_tags($listDetail[3]));

						//Create the list (or find one that already exists)
						$pikaList          = new \UserList();
						$pikaList->user_id = $patron->id;
						$pikaList->title   = $classicListTitle;
						if (!$pikaList->find(true)){
							$pikaList->description = $classicListDescription;
							$pikaList->insert();
						}

						$pikaListTitles = $pikaList->getListTitles();

						// Get a list of all titles within the list to be imported
						$classicListPage = $this->_curlLegacy($patron, 'mylists?listNum=' . $classicListId);
						// Get the table for the details
						if (preg_match('/<table[^>]*?class="patFunc"[^>]*?>(.*?)<\/table>/si', $classicListPage, $listsDetailsMatches)){
							$classicListTitlesTable = $listsDetailsMatches[1];

							// Get the bib numbers for the title
							$classicListEntryRegex = '/<input type="checkbox" name="(?:name_pfmark_cancelx)(b\\d+?)".*?<span[^>]*class="patFuncTitle(?:Main)?">(.*?)<\/span>/si';
							preg_match_all($classicListEntryRegex, $classicListTitlesTable, $bibNumberMatches, PREG_SET_ORDER);
							if (!empty($bibNumberMatches)){
								foreach ($bibNumberMatches as $bibNumberMatch){
									$shortBibNumber  = $bibNumberMatch[1];
									$fullBibNumber   = '.' . $shortBibNumber . $this->getCheckDigit($shortBibNumber);
									$classicBibTitle = strip_tags($bibNumberMatch[2]);

									// Get the grouped work for the resource
									$primaryIdentifier             = new \GroupedWorkPrimaryIdentifier();
									$primaryIdentifier->identifier = $fullBibNumber;
									$primaryIdentifier->type       = $this->accountProfile->recordSource;
									$groupedWork                   = new \GroupedWork();
									$primaryIdentifier->joinAdd($groupedWork);
									if ($primaryIdentifier->find(true)){
										//Check to see if this title is already on the list.
										$resourceOnPikaList = false;
										foreach ($pikaListTitles as $currentTitle){
											if ($currentTitle->groupedWorkPermanentId == $primaryIdentifier->permanent_id){
												$resourceOnPikaList = true;
												break;
											}
										}

										if (!$resourceOnPikaList){
											$listEntry                         = new \UserListEntry();
											$listEntry->groupedWorkPermanentId = $primaryIdentifier->permanent_id;
											$listEntry->listId                 = $pikaList->id;
											$listEntry->notes                  = '';
											$listEntry->dateAdded              = time();
											$listEntry->insert();
										}
										$results['totalTitles']++;
									}else{
										// The title is not in the resources, add an error to the results
										$errors[] = "List <strong>\"$classicListTitle\"</strong>: <em>\"$classicBibTitle\"</em> ($fullBibNumber) could not be found in the catalog and was not imported.";
									}

								}
							}else{
								$errors[] = "List <strong>\"$classicListTitle\"</strong>: No titles for, or unable to parse titles for list.";
							}
						}else{
							$errors[] = "List <strong>\"$classicListTitle\"</strong>: Unable to parse entries for list.";
						}

						$results['totalLists'] += 1;
					}
				}else{
					$errors[] = 'Unable to parse information for classic catalog lists or no lists in the classic catalog.';
				}
			}else{
				$errors[] = 'Did not find any lists in the classic catalog.';
			}
		}else{
			$errors[] = 'Failed to log into or access the classic catalog.';
		}

		if (!empty($errors)){
			$results['errors'] = $errors;
		}
		return $results;
	}

	private function _curlLegacy($patron, $pageToCall, $postParams = array(), $patronAction = true){

		$c = new Curl();

		// base url for following calls
		$vendorOpacUrl = $this->accountProfile->vendorOpacUrl;

		$headers = [
			"Accept"          => "text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5",
			"Cache-Control"   => "max-age=0",
			"Connection"      => "keep-alive",
			"Accept-Charset"  => "ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Accept-Language" => "en-us,en;q=0.5",
			"User-Agent"      => "Pika"
		];
		$c->setHeaders($headers);

		$cookie   = @tempnam("/tmp", "CURLCOOKIE");
		$curlOpts = [
			CURLOPT_CONNECTTIMEOUT    => 20,
			CURLOPT_TIMEOUT           => 60,
			CURLOPT_RETURNTRANSFER    => true,
			CURLOPT_FOLLOWLOCATION    => true,
			CURLOPT_UNRESTRICTED_AUTH => true,
			CURLOPT_COOKIEJAR         => $cookie,
			CURLOPT_COOKIESESSION     => false,
			CURLOPT_HEADER            => false,
			CURLOPT_AUTOREFERER       => true,
		];
		$c->setOpts($curlOpts);

		// first log patron in
		if ($this->accountProfile->loginConfiguration == 'name_barcode'){
			$postData = [
				'name' => $patron->cat_username,
				'code' => $patron->cat_password
			];
		}else{
			$postData = [
				'code' => $patron->cat_username,
				'pin'  => $patron->cat_password
			];

		}

		$loginUrl = $vendorOpacUrl . '/patroninfo/';
		$r        = $c->post($loginUrl, $postData);

		if ($c->isError()){
			$c->close();
			return false;
		}

		$sierraPatronId = $this->getPatronId($patron->barcode); //when logging in with pin, this is what we will find

		if(!strpos($r, (string) $sierraPatronId) && !stripos($r, (string) $patron->cat_username)) {
			// check for cas login. do cas login if possible
			$casUrl = '/iii/cas/login';
			if(stristr($r, $casUrl)) {
				$this->logger->info('Trying cas login.');
				preg_match('|<input type="hidden" name="lt" value="(.*)"|', $r, $m);
				if($m) {
					$postData['lt']       = $m[1];
					$postData['_eventId'] = 'submit';
				} else {
					return false;
				}
				$casLoginUrl = $vendorOpacUrl.$casUrl;
				$r = $c->post($casLoginUrl, $postData);
				if(!stristr($r, $patron->cat_username)) {
					$this->logger->warning('cas login failed.');
					return false;
				}
				$this->logger->info('cas login success.');
			} else {
				$this->logger->warning('login failed.');
				return false;
			}
		}

		$scope    = $this->getLibrarySierraScope(); // IMPORTANT: Scope is needed for Bookings Actions to work
		$optUrl   = $patronAction ? $vendorOpacUrl . '/patroninfo~S' . $scope . '/' . $sierraPatronId . '/' . $pageToCall
			: $vendorOpacUrl . '/' . $pageToCall;
		// Most curl calls are patron interactions, getting the bookings calendar isn't

		$c->setUrl($optUrl);
		if (!empty($postParams)){
			$r = $c->post($postParams);
		}else{
			$r = $c->get($optUrl);
		}

		if ($c->isError()){
			return false;
		}

		if (stripos($pageToCall, 'webbook?/') !== false){
			// Hack to complete booking a record
			return $c;
		}
		return $r;
	}

	/**
	 * Classic OPAC scope for legacy screen scraping calls
	 * @param bool $checkLibraryRestrictions  Whether or not to condition the use of Sierra OPAC scope by the library setting $restrictSearchByLibrary;
	 * @return mixed|string
	 */
	protected function getLibrarySierraScope($checkLibraryRestrictions = false){

		//Load the holding label for the branch where the user is physically.
		$searchLocation = \Location::getSearchLocation();
		if (!empty($searchLocation->scope)){
			return $searchLocation->scope;
		}

		$searchLibrary = \Library::getSearchLibrary();
		if (!empty($searchLibrary->scope)){
			if (!$checkLibraryRestrictions || $searchLibrary->restrictSearchByLibrary){
				return $searchLibrary->scope;
			}
		}
		return $this->getDefaultSierraScope();
	}

	protected function getDefaultSierraScope(){
		global $configArray;
		return $configArray['OPAC']['defaultScope'] ?? '93';
	}

}
