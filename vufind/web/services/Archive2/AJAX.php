<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
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
 * AJAX handler for Islandora2 / Archive2 requests.
 * URL routing: /Archive2/AJAX?method=<methodName>
 *
 * Add method names to the appropriate response-type array, then implement
 * the corresponding method in this class.
 *
 * @category Pika
 * @author   Pascal Brammeier <pika@marmot.org>
 */

require_once ROOT_DIR . '/AJAXHandler.php';
require_once ROOT_DIR . '/sys/Islandora2/I2ObjectFactory.php';
require_once ROOT_DIR . '/sys/Archive2/ExploreMore.php';

use Islandora2\I2ObjectFactory;
use Archive2\ExploreMore;

class Archive2_AJAX extends AJAXHandler {

	/** Methods that return a plain JSON object (no result-wrapper envelope). */
	protected $methodsThatRespondWithJSONUnstructured = [
		'getExploreMoreContent',
		'getTaxonomyExploreMoreContent',
		'getRelatedObjectsForPerson',
		'getRelatedObjectsForOrganization',
		'getRelatedObjectsForEvent',
		'getRelatedObjectsForPlace',
		'showSaveToListForm',
		'saveToList',
	];

	/** Methods that return a structured JSON result wrapper {result, message, ...}. */
	protected $methodsThatRespondWithJSONResultWrapper = [
		// e.g. 'saveToList',
	];

	/** Methods that return raw HTML for insertion into the page. */
	protected $methodsThatRespondWithHTML = [
		// e.g. 'getRelatedObjectsPanel',
	];

	/** Methods that write their own output and headers (e.g. binary streams, VTT). */
	protected $methodsThatRespondThemselves = [
		'fetchVtt',
		'fetchManifest',
		'fetchCantaloupeManifest',
		'fetchPDFFile',
	];

	/**
	 * Proxy a WebVTT caption file from the Islandora2 server to avoid CORS issues.
	 * Called via: /Archive2/AJAX?method=fetchVtt&path={encoded_path}
	 */
	function fetchVtt(): void {
		global $configArray;
		$baseUrl = rtrim($configArray['Islandora2']['url'] ?? '', '/');
		$path    = urldecode($_REQUEST['path'] ?? '');

		if (!$path) {
			$this->logger->error('fetchVtt called without a path parameter.');
			return;
		}

		$response = $this->proxyCurl($baseUrl . $path);
		if ($response !== null) {
			header('Content-Type: text/vtt');
			echo $response;
		}
	}

	/**
	 * Proxy a IIIF manifest from the Islandora2 server.
	 * Called via: /Archive2/AJAX?method=fetchManifest&nid={nid}
	 */
	function fetchManifest(): void {
		global $configArray;
		$baseUrl = rtrim($configArray['Islandora2']['url'] ?? '', '/');
		$nid     = (int)($_REQUEST['nid'] ?? 0);

		if (!$nid) {
			$this->logger->error('fetchManifest called without a valid nid.');
			return;
		}

		$response = $this->proxyCurl($baseUrl . '/node/' . $nid . '/manifest');
		if ($response !== null) {
			header('Content-Type: application/ld+json');
			echo $response;
		}
	}

	/**
	 * Proxy a IIIF image manifest from the Cantaloupe image server.
	 * Called via: /Archive2/AJAX?method=fetchCantaloupeManifest&sf={encoded_service_file_url}
	 */
	function fetchCantaloupeManifest(): void {
		global $configArray;
		$baseUrl        = rtrim($configArray['Islandora2']['url'] ?? '', '/');
		$serviceFileUrl = urlencode(urldecode($_REQUEST['sf'] ?? ''));

		$response = $this->proxyCurl($baseUrl . '/cantaloupe/iiif/2/' . $serviceFileUrl);
		if ($response !== null) {
			header('Content-Type: application/json;charset=utf-8');
			echo $response;
		}
	}

	/**
	 * Proxy a PDF file to avoid pdfjs hardcoded CORS.
	 */
	function fetchPDFFile(): void {
		$file = $_REQUEST['pdf_file'];

		$response = $this->proxyCurl($file);
		if ($response !== null) {
			header('Content-Type: application/pdf');
			echo $response;
		}
	}


	/**
	 * Execute a cURL GET request and return the response body, or null on failure.
	 *
	 * @param string $url
	 * @return string|null
	 */
	private function proxyCurl(string $url): ?string {
		global $configArray;
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT      => $configArray['Islandora2']['userAgent'] ?? '',
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
		]);

		$response   = curl_exec($ch);
		$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError  = curl_error($ch);
		curl_close($ch);

		if ($response === false || $statusCode !== 200) {
			$this->logger->error('proxyCurl failed.', [
				'url'        => $url,
				'statusCode' => $statusCode,
				'curlError'  => $curlError,
			]);
			return null;
		}

		return $response;
	}

	/**
	 * Build and return the rendered Explore More sidebar HTML for an Islandora2 object.
	 * Called via: /Archive2/AJAX?method=getExploreMoreContent&id={nid}
	 */
	function getExploreMoreContent(): array {
		$nid = (int)($_REQUEST['id'] ?? 0);
		if ($nid <= 0) {
			return ['success' => false, 'message' => 'A valid node id is required.'];
		}

		global $interface;
		global $timer;

		$factory = new I2ObjectFactory();
		$i2Object = $factory->fromNodeId($nid);
		if ($i2Object === null) {
			$this->logger->error('Failed to create I2Object for nid.', ['nid' => $nid]);
			return ['success' => false, 'message' => "Could not load Islandora2 object for nid $nid."];
		}
		$timer->logTime('ExploreMore: loaded I2Object');

		$exploreMore     = new ExploreMore();
		$sections        = $exploreMore->loadExploreMoreSidebar($i2Object);
		$timer->logTime('ExploreMore: loadSidebar complete');

		$interface->assign('exploreMoreSections', $sections);
		require_once ROOT_DIR . '/sys/Archive/ArchiveExploreMoreBar.php';
		global $library;
		$exploreMoreSettings = $library->exploreMoreBar;
		if (empty($exploreMoreSettings)) {
			$exploreMoreSettings = ArchiveExploreMoreBar::getDefaultArchiveExploreMoreOptions();
		}
		$interface->assign('exploreMoreSettings', $exploreMoreSettings);
		$interface->assign('archiveSections',     ExploreMore::SECTIONS);

		return [
			'success'     => true,
			'exploreMore' => $interface->fetch('explore-more-sidebar.tpl'),
		];
	}

	/**
	 * Build and return the rendered Explore More sidebar HTML for a taxonomy term page.
	 * Called via: /Archive2/AJAX?method=getTaxonomyExploreMoreContent&tid={tid}
	 */
	function getTaxonomyExploreMoreContent(): array {
		$tid = (int)($_REQUEST['tid'] ?? 0);
		if ($tid <= 0) {
			return ['success' => false, 'message' => 'A valid taxonomy term id is required.'];
		}

		global $interface;
		global $timer;
		global $library;

		require_once ROOT_DIR . '/sys/Islandora2/TaxonomyFactory.php';
		$factory = new \Islandora2\TaxonomyFactory();
		$term    = $factory->fromTid($tid);
		if ($term === null) {
			$this->logger->error('Failed to create taxonomy term for tid.', ['tid' => $tid]);
			return ['success' => false, 'message' => "Could not load taxonomy term for tid $tid."];
		}
		$timer->logTime('ExploreMore: loaded taxonomy term');

		$exploreMore = new ExploreMore();
		$sections    = $exploreMore->loadTaxonomyExploreMoreSidebar($term);
		$timer->logTime('ExploreMore: loadTaxonomySidebar complete');

		$interface->assign('exploreMoreSections', $sections);
		require_once ROOT_DIR . '/sys/Archive/ArchiveExploreMoreBar.php';
		$exploreMoreSettings = $library->exploreMoreBar;
		if (empty($exploreMoreSettings)) {
			$exploreMoreSettings = \ArchiveExploreMoreBar::getDefaultArchiveExploreMoreOptions();
		}
		$interface->assign('exploreMoreSettings', $exploreMoreSettings);
		$interface->assign('archiveSections',     ExploreMore::SECTIONS);

		return [
			'success'     => true,
			'exploreMore' => $interface->fetch('explore-more-sidebar.tpl'),
		];
	}

	/**
	 * Fetch the first 20 archive objects related to a Person taxonomy term and
	 * return rendered tile HTML for injection into the Related Objects accordion panel.
	 * Called via: /Archive2/AJAX?method=getRelatedObjectsForPerson&name={person name}
	 */
	function getRelatedObjectsForPerson(): array {
		$name = trim(strip_tags($_REQUEST['name'] ?? ''));
		if (empty($name)) {
			return ['success' => false, 'message' => 'Person name is required.'];
		}
		if (str_contains($name, '/') || str_contains($name, '\\')) {
			$this->logger->warning('getRelatedObjectsForPerson: rejected name containing slash.', ['name' => $name]);
			return ['success' => false, 'message' => 'Invalid person name.'];
		}

		global $interface;

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter('sm_related_person:"' . $name . '"');
		$searchObject->setSort('sm_field_edtf_date_created desc');
		$searchObject->setLimit(20);
		$result = $searchObject->processSearch(true, false);

		$total = (int)($result['response']['numFound'] ?? 0);
		if ($total === 0) {
			return ['success' => true, 'hasResults' => false, 'html' => ''];
		}

		$tiles = [];
		foreach ($result['response']['docs'] as $doc) {
			/** @var \Islandora2Driver $driver */
			$driver  = \RecordDriverFactory::initRecordDriver($doc);
			$tiles[] = [
				'title' => $driver->getTitle(),
				'image' => $driver->getBookcoverUrl('medium'),
				'link'  => $driver->getRecordUrl(),
			];
		}

		$searchUrl = '/Archive2/Results?' . urlencode('filter[]') . '=sm_related_person:' . urlencode('"' . $name . '"');

		$interface->assign('relatedObjects',          $tiles);
		$interface->assign('relatedObjectsTotal',     $total);
		$interface->assign('relatedObjectsSearchUrl', $searchUrl);

		return [
			'success'    => true,
			'hasResults' => true,
			'html'       => $interface->fetch('Archive2/panels/relatedObjectsContent.tpl'),
		];
	}

	/**
	 * Fetch the first 20 archive objects related to an Event taxonomy term.
	 * Called via: /Archive2/AJAX?method=getRelatedObjectsForEvent&name={event name}
	 */
	function getRelatedObjectsForEvent(): array {
		$name = trim(strip_tags($_REQUEST['name'] ?? ''));
		if (empty($name)) {
			return ['success' => false, 'message' => 'Event name is required.'];
		}
		if (str_contains($name, '/') || str_contains($name, '\\')) {
			$this->logger->warning('getRelatedObjectsForEvent: rejected name containing slash.', ['name' => $name]);
			return ['success' => false, 'message' => 'Invalid event name.'];
		}

		global $interface;

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter('sm_related_event:"' . $name . '"');
		$searchObject->setSort('sm_field_edtf_date_created desc');
		$searchObject->setLimit(20);
		$result = $searchObject->processSearch(true, false);

		$total = (int)($result['response']['numFound'] ?? 0);
		if ($total === 0) {
			return ['success' => true, 'hasResults' => false, 'html' => ''];
		}

		$tiles = [];
		foreach ($result['response']['docs'] as $doc) {
			/** @var \Islandora2Driver $driver */
			$driver  = \RecordDriverFactory::initRecordDriver($doc);
			$tiles[] = [
				'title' => $driver->getTitle(),
				'image' => $driver->getBookcoverUrl('medium'),
				'link'  => $driver->getRecordUrl(),
			];
		}

		$searchUrl = '/Archive2/Results?' . urlencode('filter[]') . '=sm_related_event:' . urlencode('"' . $name . '"');

		$interface->assign('relatedObjects',          $tiles);
		$interface->assign('relatedObjectsTotal',     $total);
		$interface->assign('relatedObjectsSearchUrl', $searchUrl);

		return [
			'success'    => true,
			'hasResults' => true,
			'html'       => $interface->fetch('Archive2/panels/relatedObjectsContent.tpl'),
		];
	}

	/**
	 * Fetch the first 20 archive objects related to a Place taxonomy term.
	 * Called via: /Archive2/AJAX?method=getRelatedObjectsForPlace&name={place name}
	 */
	function getRelatedObjectsForPlace(): array {
		$name = trim(strip_tags($_REQUEST['name'] ?? ''));
		if (empty($name)) {
			return ['success' => false, 'message' => 'Place name is required.'];
		}
		if (str_contains($name, '/') || str_contains($name, '\\')) {
			$this->logger->warning('getRelatedObjectsForPlace: rejected name containing slash.', ['name' => $name]);
			return ['success' => false, 'message' => 'Invalid place name.'];
		}

		global $interface;

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter('sm_related_place:"' . $name . '"');
		$searchObject->setSort('sm_field_edtf_date_created desc');
		$searchObject->setLimit(20);
		$result = $searchObject->processSearch(true, false);

		$total = (int)($result['response']['numFound'] ?? 0);
		if ($total === 0) {
			return ['success' => true, 'hasResults' => false, 'html' => ''];
		}

		$tiles = [];
		foreach ($result['response']['docs'] as $doc) {
			/** @var \Islandora2Driver $driver */
			$driver  = \RecordDriverFactory::initRecordDriver($doc);
			$tiles[] = [
				'title' => $driver->getTitle(),
				'image' => $driver->getBookcoverUrl('medium'),
				'link'  => $driver->getRecordUrl(),
			];
		}

		$searchUrl = '/Archive2/Results?' . urlencode('filter[]') . '=sm_related_place:' . urlencode('"' . $name . '"');

		$interface->assign('relatedObjects',          $tiles);
		$interface->assign('relatedObjectsTotal',     $total);
		$interface->assign('relatedObjectsSearchUrl', $searchUrl);

		return [
			'success'    => true,
			'hasResults' => true,
			'html'       => $interface->fetch('Archive2/panels/relatedObjectsContent.tpl'),
		];
	}

	/**
	 * Fetch the first 20 archive objects related to an Organization taxonomy term.
	 * Called via: /Archive2/AJAX?method=getRelatedObjectsForOrganization&name={org name}
	 */
	function getRelatedObjectsForOrganization(): array {
		$name = trim(strip_tags($_REQUEST['name'] ?? ''));
		if (empty($name)) {
			return ['success' => false, 'message' => 'Organization name is required.'];
		}
		if (str_contains($name, '/') || str_contains($name, '\\')) {
			$this->logger->warning('getRelatedObjectsForOrganization: rejected name containing slash.', ['name' => $name]);
			return ['success' => false, 'message' => 'Invalid organization name.'];
		}

		global $interface;

		/** @var \SearchObject_Islandora2 $searchObject */
		$searchObject = \SearchObjectFactory::initSearchObject('Islandora2');
		$searchObject->init();
		$searchObject->addFilter('sm_related_organization:"' . $name . '"');
		$searchObject->setSort('sm_field_edtf_date_created desc');
		$searchObject->setLimit(20);
		$result = $searchObject->processSearch(true, false);

		$total = (int)($result['response']['numFound'] ?? 0);
		if ($total === 0) {
			return ['success' => true, 'hasResults' => false, 'html' => ''];
		}

		$tiles = [];
		foreach ($result['response']['docs'] as $doc) {
			/** @var \Islandora2Driver $driver */
			$driver  = \RecordDriverFactory::initRecordDriver($doc);
			$tiles[] = [
				'title' => $driver->getTitle(),
				'image' => $driver->getBookcoverUrl('medium'),
				'link'  => $driver->getRecordUrl(),
			];
		}

		$searchUrl = '/Archive2/Results?' . urlencode('filter[]') . '=sm_related_organization:' . urlencode('"' . $name . '"');

		$interface->assign('relatedObjects',          $tiles);
		$interface->assign('relatedObjectsTotal',     $total);
		$interface->assign('relatedObjectsSearchUrl', $searchUrl);

		return [
			'success'    => true,
			'hasResults' => true,
			'html'       => $interface->fetch('Archive2/panels/relatedObjectsContent.tpl'),
		];
	}

	/**
	 * @return array
	 */
	function showSaveToListForm(){
		global $interface;

		$nid = (int)($_REQUEST['id'] ?? 0);
		if ($nid <= 0) {
			return ['success' => false, 'message' => 'A valid node id is required.'];
		}
		$interface->assign('id', $nid);

		require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
		require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';

		//Get a list of all lists for the user
		$containingLists    = [];
		$nonContainingLists = [];
		$listsTooLarge      = [];

		$userLists          = new UserList();
		$userLists->user_id = UserAccount::getActiveUserId();
		$userLists->deleted = 0;
		$userLists->orderBy('dateUpdated > unix_timestamp()-300 desc, if(dateUpdated > unix_timestamp()-300 , dateUpdated, 0) desc, title');
		// Sort lists first by any that have been recently updated (within the last 5 mins)  eg a user is currently build a specific list
		// second sort factor for multiple lists updated within the last five minutes is the last updated time; other lists come next
		// finally alphabetical order for lists that haven't been updated recently
		//This complex sorting allows for a list that has had an entry added to it recently,
		// to show as the default selected list in the dropdown list of which user lists to add a title to
		$userLists->find();
		while ($userLists->fetch()){
			if ($userLists->numValidListItems() >= 2000){
				$listsTooLarge[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
			//Check to see if the user has already added the title to the list.
			$userListEntry                         = new UserListEntry();
			$userListEntry->listId                 = $userLists->id;
			$userListEntry->groupedWorkPermanentId = $nid;
			if ($userListEntry->find(true)){
				$containingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}else{
				$nonContainingLists[] = [
					'id'    => $userLists->id,
					'title' => $userLists->title,
				];
			}
		}

		$interface->assign('containingLists', $containingLists);
		$interface->assign('nonContainingLists', $nonContainingLists);
		$interface->assign('largeLists', $listsTooLarge);

		$results = [
			'title'        => 'Add To List',
			'modalBody'    => $interface->fetch('GroupedWork/save.tpl'),
			'modalButtons' => "<button class='tool btn btn-primary' onclick='Pika.Archive2.saveToList(\"{$nid}\"); return false;'>Save To List</button>",
		];
		return $results;
	}

	function saveToList(){
		$result = [];

		if (!UserAccount::isLoggedIn()){
			$result['success'] = false;
			$result['message'] = 'Please log in before adding a title to list.';
		}else{
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserList.php';
			require_once ROOT_DIR . '/sys/LocalEnrichment/UserListEntry.php';
			$result['success'] = true;
			$id                = urldecode($_REQUEST['id']);
			if (!preg_match("/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|[0-9-]+$/i", $id)){
				// Is not a valid grouped work Id or archive PID
				$result['success'] = false;
				$result['message'] = 'That is not a valid title to add to the list.';
			}else{
				$listId = $_REQUEST['listId'];
				$notes  = $_REQUEST['notes'];

				//Check to see if we need to create a list
				$userList = new UserList();
				$listOk   = true;
				if (empty($listId)){
					$existingList          = new UserList();
					$existingList->user_id = UserAccount::getActiveUserId();
					$existingList->title   = 'My Favorites';
					$existingList->deleted = 0;
					//Make sure we don't create duplicate My Favorites List
					if ($existingList->find(true)){
						$userList = $existingList;
					}else{
						$userList->title       = 'My Favorites';
						$userList->user_id     = UserAccount::getActiveUserId();
						$userList->public      = 0;
						$userList->description = '';
						$userList->insert();
					}

				}else{
					$userList->id = $listId;
					if (!$userList->find(true)){
						$result['success'] = false;
						$result['message'] = 'Sorry, we could not find that list in the system.';
						$listOk            = false;
					}
				}

				if ($listOk){
					$userListEntry                         = new UserListEntry();
					$userListEntry->listId                 = $userList->id;
					$userListEntry->groupedWorkPermanentId = $id;

					$existingEntry = false;
					if ($userListEntry->find(true)){
						$existingEntry = true;
					}
					$userListEntry->notes     = strip_tags($notes);
					$userListEntry->dateAdded = time();
					if ($userList->defaultSort == 'custom'){
						$weight = $userList->getNextWeightForUserDefinedSort();
						if ($weight){
							$userListEntry->weight = $weight;
						}
					}
					if ($existingEntry){
						$userListEntry->update();
					}else{
						$userListEntry->insert();
					}

					$result['success'] = true;
					$result['message'] = 'This title was saved to your list successfully.';
				}
			}
		}

		return $result;
	}

}