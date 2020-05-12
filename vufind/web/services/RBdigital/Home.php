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


use Curl\Curl;
use Pika\App;

require_once ROOT_DIR . '/services/Record/Home.php';
require_once ROOT_DIR . '/RecordDrivers/RBdigitalMagazineRecordDriver.php';

/**
 * Class RBdigital_Home
 */
class RBdigital_Home extends Record_Home {
	private App $app;
	private Curl $curl;
	public function __construct($record_id = null)
	{
		$this->app  = new App();
		$this->curl = new Curl();

		$this->webServiceBaseUrl = $this->app->config['RBdigital']['webServiceUrl'] . '/v1/libraries/' .
		 $this->app->config['RBdigital']['libraryId'] . '/';
		$this->tokenBaseUrl      = $this->app->config['RBdigital']['webServiceUrl'] . '/v1/rpc/libraries/' .
		 $this->app->config['RBdigital']['libraryId'] . '/patrons/';
		$this->userInterfaceUrl  = $this->app->config['RBdigital']['userInterfaceUrl'];

		$headers = [
		 'Accept'        => 'application/json',
		 'Authorization' => 'basic ' . $this->app->config['RBdigital']['apiToken'],
		 'Content-Type'  => 'application/json'
		];

		$this->curl->setHeaders($headers);
		parent::__construct($record_id);
	}

	function launch()
	{
		//parent::launch();
		global $interface;
		global $timer;
		global $configArray;

		$this->loadCitations();
		$timer->logTime('Loaded Citations');

		if (isset($_REQUEST['searchId'])){
			$_SESSION['searchId'] = $_REQUEST['searchId'];
		}
		if (isset($_SESSION['searchId'])){
			$interface->assign('searchId', $_SESSION['searchId']);
		}

		// Set Show in Main Details Section options for templates
		// (needs to be set before moreDetailsOptions)
		global $library;
		foreach ($library->showInMainDetails as $detailOption){
			$interface->assign($detailOption, true);
		}

		//Get the actions for the record
		$actions = $this->recordDriver->getRecordActionsFromIndex();
		//Get the magazine id from the url
		foreach ($actions as $action) {
			if($action['title'] == 'Access Online') {
				$idRegExp = "/.*\/(\d*)$/";
				preg_match($idRegExp, $action['url'], $m);
				$magazineId = $m[1];
			}
		}

		$issues = $this->getIssues($magazineId);
		$interface->assign('issues', $issues);
		$interface->assign('actions', $actions);

		$interface->assign('moreDetailsOptions', $this->recordDriver->getMoreDetailsOptions());
		$exploreMoreInfo = $this->recordDriver->getExploreMoreInfo();
		$interface->assign('exploreMoreInfo', $exploreMoreInfo);

		if($semanticData = $this->recordDriver->getSemanticData() && !empty($semanticData)) {
			$interface->assign('semanticData', json_encode($semanticData, JSON_PRETTY_PRINT));
		} else {
			$interface->assign('semanticData', false);
		}

		// Display Page
		if ($configArray['Catalog']['showExploreMoreForFullRecords']){
			$interface->assign('showExploreMore', true);
		}

		$title = $this->recordDriver->getShortTitle();
		if (!empty($this->recordDriver->getSubtitle())){
			if (!empty($title)){
				$title .= ' : ';
			}
			$title .=  $this->recordDriver->getSubtitle();
		}
		$this->display('view.tpl', $title);
	}

	public function getIssues($magazineId) {
		$pageIndex = 0;
		$url = $this->webServiceBaseUrl . 'magazines/' . $magazineId . '/issues?pageIndex=' . $pageIndex . '&pageSize=100';

		$res = $this->curl->get($url);
		$issues = [];
		foreach($res->resultSet as $result) {
			$issue = [];
			$issue['issueId'] = $result->item->issueId;
			$issue['magazineId'] = $result->item->magazineId;
			$issue['coverDate'] = $result->item->coverDate;
			$issue['image'] = $result->item->images[0]->url;

			$issues[] = $issue;
		}
		return $issues;
	}
}
