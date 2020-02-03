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
 * Handles searching DPLA and returning results
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/9/15
 * Time: 3:09 PM
 */

class DPLA {
	public function getDPLAResults($searchTerm, $numResults = 5){
		global $configArray;
		$results = array();
		if (!empty($configArray['DPLA']['enabled'])) {
			$queryUrl    = "http://api.dp.la/v2/items?api_key={$configArray['DPLA']['apiKey']}&page_size=$numResults&q=" . urlencode($searchTerm);

			$responseRaw = @file_get_contents($queryUrl);
			if ($responseRaw) {
				$responseData = json_decode($responseRaw);
				//Uncomment to view full response
				//echo(print_r($responseData, true));

				//Extract, title, author, source, and the thumbnail
				foreach ($responseData->docs as $curDoc) {
					$curResult = array();

					$curResult['id']           = @$this->getDataForNode($curDoc->id);
					$curResult['link']         = @$this->getDataForNode($curDoc->isShownAt);
					$curResult['object']       = @$this->getDataForNode($curDoc->object);
					$curResult['image']        = @$this->getDataForNode($curDoc->object);
					$curResult['title']        = @$this->getDataForNode($curDoc->sourceResource->title);
					$curResult['label']        = @$this->getDataForNode($curDoc->sourceResource->title);
					$curResult['date']         = @$this->getDataForNode($curDoc->sourceResource->date->displayDate);
					$curResult['description']  = @$this->getDataForNode($curDoc->sourceResource->description);
					$curResult['dataProvider'] = @$this->getDataForNode($curDoc->dataProvider);
					$curResult['format']       = @$this->getDataForNode($curDoc->originalRecord->format);
					if ($curResult['format'] == "") {
						$curResult['format'] = @$this->getDataForNode($curDoc->originalRecord->type);
					}
					if ($curResult['format'] == "") {
						$curResult['format'] = 'Not Provided';
					}
					$curResult['publisher'] = @$this->getDataForNode($curDoc->sourceResource->publisher);
					if ($curResult['publisher'] == "") {
						$curResult['publisher'] = @$this->getDataForNode($curDoc->originalRecord->publisher);
					}
					$results[] = $curResult;
				}
			}
		}

		return array(
				'firstRecord' => 0,
				'lastRecord'  => count($results),
				'resultTotal' => isset($responseData->count) ? $responseData->count : 0,
				'records'     => $results
		);
	}

	public function getDataForNode($node){
		if (empty($node)){
			return "";
		}else if (is_array($node)){
			return $node[0];
		}else{
			return $node;
		}
	}


	public function formatResults($results, $showDescription = true) {
		$formattedResults = "";
		if (count($results) > 0){
			global $interface;
			$interface->assign('searchResults', $results);
			$interface->assign('showDplaDescription', $showDescription);
			$formattedResults = $interface->fetch('Search/dplaResults.tpl');
		}
		return $formattedResults;
	}

	public function formatCombinedResults($results, $showDescription = true) {
		$formattedResults = "";
		if (count($results) > 0){
			global $interface;
			$interface->assign('searchResults', $results);
			$interface->assign('showDplaDescription', $showDescription);
			$formattedResults = $interface->fetch('Search/dplaCombinedResults.tpl');
		}
		return $formattedResults;
	}
}
