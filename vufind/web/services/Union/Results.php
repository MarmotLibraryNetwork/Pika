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

/**
 * General Class to hold common methods used by Search Results classes.  Those classes should extend this
 * class instead of duplicating code across the Result processing classes
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 11/30/2020
 *
 */

require_once ROOT_DIR . '/Action.php';

abstract class Union_Results extends Action {

	/**
	 * Send the user alternative outputs of search results such as RSS feed, spreadsheet files
	 *
	 * @param SearchObject_Base $searchObject
	 */
	protected function processAlternateOutputs(SearchObject_Base $searchObject){
		// Build RSS Feed for Results (if requested)
		$view = $searchObject->getView();
		switch ($view){
			case 'rss':
				// Throw the XML to screen
				echo $searchObject->buildRSS();
				// And we're done
				exit;
			case 'excel':
				// Throw the Excel spreadsheet to screen for download
				echo $searchObject->buildExcel();
				// And we're done
				exit;
		}
	}

	protected function setPageTitle(string $pageTitle){
		if (strlen($pageTitle) > 20){
			$pageTitle = mb_substr($pageTitle, 0, 20) . '...';
		}
		$pageTitle .= ' | Search Results';
		return $pageTitle;
	}

	protected function displaySolrError($error){
		global $interface;
		// If it's a parse error or the user specified an invalid field, we
		// should display an appropriate message:
		if (stristr($error['msg'], 'org.apache.lucene.queryParser.ParseException') || preg_match('/^undefined field/', $error['msg'])){
//								|| stristr($error['msg'], 'org.apache.solr.search.SyntaxError')
			// Genealogy_Results treated the syntax error the same as these. Including in case we want to use that.

			$interface->assign('parseError', $error['msg']);

//			if (preg_match('/^undefined field/', $error['msg'])) {
//				//TODO: this should display additional information when automatically redirecting
//
//				// Setup to try as a possible subtitle search
//				$fieldName = trim(str_replace('undefined field', '', $error['msg'], $replaced)); // strip out the phrase 'undefined field' to get just the fieldname
//				$original = urlencode("$fieldName:");
//				if ($replaced === 1 && !empty($fieldName) && strpos($_SERVER['REQUEST_URI'], $original)) {
//					// ensure only 1 replacement was done, that the fieldname isn't an empty string, and the label is in fact in the Search URL
//					$new = urlencode("$fieldName :"); // include space in between the field name & colon to avoid the parse error
//					$thisUrl = str_replace($original, $new, $_SERVER['REQUEST_URI'], $replaced);
//					if ($replaced === 1) { // ensure only one modification was made
//						header('Location: ' . $thisUrl);
//						exit();
//					}
//				}
//			}

		} else {
			// Unexpected error -- let's treat this as a fatal condition.
			global $pikaLogger;
			$pikaLogger->error('Error processing search. Solr Returned: ' . print_r($error, true));
			PEAR_Singleton::raiseError(new PEAR_Error('Error processing search.'));
		}
	}
}