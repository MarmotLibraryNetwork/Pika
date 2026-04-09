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
		// e.g. 'fetchManifest',
	];

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
		$interface->assign('exploreMoreSettings', ExploreMore::buildSettings());
		$interface->assign('archiveSections',     ExploreMore::SECTIONS);

		return [
			'success'     => true,
			'exploreMore' => $interface->fetch('explore-more-sidebar.tpl'),
		];
	}

}