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
 * Description goes here
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 2/15/2016
 * Time: 6:09 PM
 */
class EBSCO_Results extends Action{
	function launch() {
		global $interface;
		global $timer;

		//Include Search Engine
		require_once ROOT_DIR . '/sys/Ebsco/EDS_API.php';
		$searchObject = EDS_API::getInstance();
		$timer->logTime('Include search engine');

		$interface->setPageTitle('EBSCO Search Results');

		$sort = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : null;
		$filters = isset($_REQUEST['filter']) ? $_REQUEST['filter'] : array();
		$searchObject->getSearchResults($_REQUEST['lookfor'], $sort, $filters);

		$displayQuery = $_REQUEST['lookfor'];
		$pageTitle = $displayQuery;
		if (strlen($pageTitle) > 20){
			$pageTitle = substr($pageTitle, 0, 20) . '...';
		}

		$interface->assign('qtime',               round($searchObject->getQuerySpeed(), 2));
		$interface->assign('lookfor',             $displayQuery);

		// Big one - our results //
		$recordSet = $searchObject->getResultRecordHTML();
		$interface->assign('recordSet', $recordSet);
		$timer->logTime('load result records');

		$interface->assign('sortList',   $searchObject->getSortList());

		$summary = $searchObject->getResultSummary();
		$interface->assign('recordCount', $summary['resultTotal']);
		$interface->assign('recordStart', $summary['startRecord']);
		$interface->assign('recordEnd',   $summary['endRecord']);

		$appliedFacets = $searchObject->getAppliedFilters();
		$interface->assign('filterList', $appliedFacets);
		$facetSet = $searchObject->getFacetSet();
		$interface->assign('sideFacetSet', $facetSet);

		if ($summary['resultTotal'] > 0){
			$link    = $searchObject->renderLinkPageTemplate();
			$options = array('totalItems' => $summary['resultTotal'],
					'fileName' => $link,
					'perPage' => $summary['perPage']);
			$pager   = new VuFindPager($options);
			$interface->assign('pageLinks', $pager->getLinks());
		}

		//Setup explore more
		$showExploreMoreBar = true;
		if (isset($_REQUEST['page']) && $_REQUEST['page'] > 1){
			$showExploreMoreBar = false;
		}
		$exploreMore = new ExploreMore();
		$exploreMoreSearchTerm = $exploreMore->getExploreMoreQuery();
		$interface->assign('exploreMoreSection', 'ebsco');
		$interface->assign('showExploreMoreBar', $showExploreMoreBar);
		$interface->assign('exploreMoreSearchTerm', $exploreMoreSearchTerm);

		$displayTemplate = 'EBSCO/list-list.tpl'; // structure for regular results
		$interface->assign('breadcrumbText', $searchObject->displayQuery());
		$interface->assign('subpage', $displayTemplate);
		$interface->assign('sectionLabel', 'EBSCO Research Databases');
		$this->display($summary['resultTotal'] > 0 ? 'list.tpl' : 'list-none.tpl', $pageTitle, 'EBSCO/results-sidebar.tpl');
	}
}
