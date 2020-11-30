<?php
/*
 * Copyright (C) 2020  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
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

	protected function processAllRangeFilters(SearchObject_Base $searchObject, $dateFilters = ['publishDate'], $rangeFilters = ['lexile_score', 'accelerated_reader_reading_level', 'accelerated_reader_point_value']){
		$yearFilters  = $this->processYearFilters($searchObject, $dateFilters);
		$rangeFilters = $this->processRangeFilters($searchObject, $rangeFilters);
		$filters      = array_merge($yearFilters, $rangeFilters);
		if (!empty($filters)){
			foreach ($filters as $filter){
				$searchObject->addFilter($filter);
			}
			$searchUrl = $searchObject->renderSearchUrl();
			header("Location: $searchUrl");
			exit();
		}
	}

	/**
	 * @param SearchObject_Base $searchObject
	 * @param string[] $rangeFilters
	 */
	private function processRangeFilters(SearchObject_Base $searchObject, $rangeFilters = []){
		//Check to see if the year has been set and if so, convert to a filter and resend.
		$filters = [];
		foreach ($rangeFilters as $rangeFilter){
			if (!empty($_REQUEST[$rangeFilter . 'from']) || !empty($_REQUEST[$rangeFilter . 'to'])){
				$from = preg_match('/^\d+(\.\d*)?$/', $_REQUEST[$rangeFilter . 'from']) ? $_REQUEST[$rangeFilter . 'from'] : '*';
				$to   = preg_match('/^\d+(\.\d*)?$/', $_REQUEST[$rangeFilter . 'to']) ? $_REQUEST[$rangeFilter . 'to'] : '*';
				if ($from != '*' || $to != '*'){
					$filterStr  = "$rangeFilter:[$from TO $to]";
					$filterList = $searchObject->getFilterList();
					foreach ($filterList as $facets){
						foreach ($facets as $facet){
							if ($facet['field'] == $rangeFilter){
								$searchObject->removeFilter($facet['field'] . ':' . $facet['value']);
								break;
							}
						}
					}
					$filters[] = $filterStr;
				}
			}
		}
		return $filters;
	}

	/**
	 * @param SearchObject_Base $searchObject
	 * @param string[] $dateFilters
	 */
	private function processYearFilters(SearchObject_Base $searchObject, $dateFilters = []){
		//Check to see if the year has been set and if so, convert to a filter and resend.
		$filters = [];
		foreach ($dateFilters as $dateFilter){
			if (!empty($_REQUEST[$dateFilter . 'yearfrom']) || !empty($_REQUEST[$dateFilter . 'yearto'])){
				$yearFrom = $this->processYearField($dateFilter . 'yearfrom');
				$yearTo   = $this->processYearField($dateFilter . 'yearto');
				if ($yearFrom != '*' || $yearTo != '*'){
					$filterStr  = "$dateFilter:[$yearFrom TO $yearTo]";
					$filterList = $searchObject->getFilterList();
					foreach ($filterList as $facets){
						foreach ($facets as $facet){
							if ($facet['field'] == $dateFilter){
								$searchObject->removeFilter($facet['field'] . ':' . $facet['value']);
								break;
							}
						}
					}
					$filters[] = $filterStr;
				}
			}
		}
		return $filters;
	}

	protected function processYearField($yearField){
		$year = preg_match('/^\d{2,4}$/', $_REQUEST[$yearField]) ? $_REQUEST[$yearField] : '*';
		if (strlen($year) == 2){
			$year = '19' . $year;
		}elseif (strlen($year) == 3){
			$year = '0' . $year;
		}
		return $year;
	}
}