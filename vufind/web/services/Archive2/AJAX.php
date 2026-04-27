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
		'getRelatedObjectsForPerson',
		'getRelatedObjectsForOrganization',
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

}