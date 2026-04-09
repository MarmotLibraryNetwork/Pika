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

class Archive2_AJAX extends AJAXHandler {

	/** Methods that return a plain JSON object (no result-wrapper envelope). */
	protected $methodsThatRespondWithJSONUnstructured = [
		// e.g. 'getObjectInfo',
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

}