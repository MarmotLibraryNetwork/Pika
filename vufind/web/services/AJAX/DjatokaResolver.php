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
 * Resove
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 8/26/2016
 * Time: 2:15 PM
 */

require_once ROOT_DIR . '/Action.php';
class DjatokaResolver extends Action{

	function launch() {
		//Pass the request to the Islandora server for processing

		global $configArray;
		$queryString = $_SERVER['QUERY_STRING'];
		$queryString = str_replace('module=AJAX&', '', $queryString);
		$queryString = str_replace('action=DjatokaResolver&', '', $queryString);
		if (substr($queryString, 0, 1) == '&'){
			$queryString = substr($queryString, 1);
		}
		$queryString = str_replace('https', 'http', $queryString);
		$baseRepositoryUrl = $configArray['Islandora']['repositoryUrl'];
		$baseRepositoryUrl = str_replace('https', 'http', $baseRepositoryUrl);
		$requestUrl = $baseRepositoryUrl . '/adore-djatoka/resolver?' . $queryString;

		try{
			$response = @file_get_contents($requestUrl);
			if (!$response){
				$response = json_encode(array(
						'success' => false,
						'message' => 'Could not load from the specified URL ' . $requestUrl
				));
			}
		}catch (Exception $e){
			$response = json_encode(array(
					'success' => false,
					'message' => $e
			));
		}

		echo($response);
	}
}
