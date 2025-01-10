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

require_once ROOT_DIR  . '/Action.php';
require_once ROOT_DIR  . '/RecordDrivers/Factory.php';
require_once ROOT_DIR . '/services/SourceAndId.php';

abstract class Record_Record extends Action {
	/** @var SourceAndId $sourceAndId */
	public $sourceAndId;

	/** @var MarcRecord|HooplaRecordDriver $recordDriver */
	protected $recordDriver;

	function __construct($recordId = null){
		//Load basic information needed in subclasses
		$this->sourceAndId = new SourceAndId(empty($recordId) ? $_GET['id'] : $recordId);

		//Check to see if the record exists within the resources table
		$this->recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId);
		if (is_null($this->recordDriver) || !$this->recordDriver->isValid()){
			// initRecordDriverById itself does a validity check and returns null if not.

			global $configArray;
			if ($this->sourceAndId->getSource() == 'ils' && $configArray['Catalog']['ils'] == 'Sierra'){
				// Redirect Sierra Record Ids without check digit present to the URL with the record checkdigit present
				$newRecordId = $this->buildrecordIdWithCheckDigit($this->sourceAndId->getRecordId());
				if ($newRecordId){
					$this->sourceAndId  = new SourceAndId($this->sourceAndId->getSource() . ':' . $newRecordId);
					$this->recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId);
					if ($this->recordDriver->isValid()){
						global $module;
						header("Location: /$module/$newRecordId");
						die;
					}
				}
				$this->displayInvalidRecord();
			}
		}

		global $interface;
		$interface->assign('id', $this->sourceAndId->getRecordId());
		$interface->assign('recordDriver', $this->recordDriver);

		$groupedWork = $this->recordDriver->getGroupedWorkDriver();
		if (is_null($groupedWork) || !$groupedWork->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->displayInvalidRecord();
		}

		$this->setClassicViewLinks();

		if (str_starts_with($this->sourceAndId->getRecordId(), '.')){
			$interface->assign('shortId', substr($this->sourceAndId->getRecordId(), 1));
		}else{
			$interface->assign('shortId', $this->sourceAndId->getRecordId());
		}

		// Retrieve User Search History
		$interface->assign('lastsearch', $_SESSION['lastSearchURL'] ?? false);
		//TODO camel case lastsearch

		// Send down text for inclusion in breadcrumbs
		$interface->assign('breadcrumbText', $this->recordDriver->getBreadcrumb());

		// Send down legal export formats (if any):
		$interface->assign('exportFormats', $this->recordDriver->getExportFormats());

		//Get Next/Previous Links
		$searchSource = $_REQUEST['searchSource'] ?? 'local';
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$searchObject->getNextPrevLinks();

	}

	/**
	 * Set any needed URL links to "Classic" Record views.
	 * Typically these are pages that are native to the ILS
	 * that Pika is replacing.
	 */
	protected function setClassicViewLinks(){
		if ($this->sourceAndId->getSource() == 'ils'){
			global $configArray;
			global $interface;

			$recordId = $this->sourceAndId->getRecordId();
			switch ($configArray['Catalog']['ils']){
				case 'Sierra':
					$catalogConnection = CatalogFactory::getCatalogConnectionInstance(); // This will use the $activeRecordIndexingProfile to get the catalog connector
					if (!empty($catalogConnection->accountProfile->vendorOpacUrl)){
						global $searchSource;
						$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
						$classicId          = substr($recordId, 1, strlen($recordId) - 2);
						$searchLocation     = Location::getSearchLocation($searchSource);
						if (!empty($searchLocation->ilsLocationId)){
							$sierraOpacScope = $searchLocation->ilsLocationId;
						}else{
							$searchLibrary   = Library::getSearchLibrary($searchSource);
							$sierraOpacScope = $searchLibrary ? $searchLibrary->scope : (empty($configArray['OPAC']['defaultScope']) ? '93' : $configArray['OPAC']['defaultScope']);
						}
						$interface->assign('classicUrl', $classicOpacBaseURL . "/record=$classicId&amp;searchscope={$sierraOpacScope}");
					}

					break;
				case 'Polaris':
					$catalogConnection = CatalogFactory::getCatalogConnectionInstance(); // This will use the $activeRecordIndexingProfile to get the catalog connector
					if (!empty($catalogConnection->accountProfile->vendorOpacUrl)){
						$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
						$interface->assign('classicUrl', rtrim($classicOpacBaseURL, '/') . '/search/title.aspx?ctx=1.1033.0.0.6&pos=1&cn=' . $recordId);
						// Based on Clearview's Polaris OPAC
					}
					break;
					case 'Koha':
					$catalogConnection  = CatalogFactory::getCatalogConnectionInstance(); // This will use the $activeRecordIndexingProfile to get the catalog connector
					$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
					$interface->assign('classicUrl', $classicOpacBaseURL . '/cgi-bin/koha/opac-detail.pl?biblionumber=' . $recordId);
					$interface->assign('staffClientUrl', $configArray['Catalog']['staffClientUrl'] . '/cgi-bin/koha/catalogue/detail.pl?biblionumber=' . $recordId);
					break;
				case 'CarlX':
					$shortId = str_replace('CARL', '', $recordId);
					$shortId = ltrim($shortId, '0');
					$interface->assign('staffClientUrl', $configArray['Catalog']['staffClientUrl'] . '/Items/' . $shortId);
					break;
			}
		}
	}

	/**
	 *  Display Invalid Record page for any record module including sideloaded records
	 */
	function displayInvalidRecord(){
		global $interface;
		$module = $interface->getVariable('module');

		$mainTemplate = $module == 'Record' ? 'invalidRecord.tpl' :'../Record/invalidRecord.tpl';
		$this->display($mainTemplate, 'Invalid Record');
		die;
	}

	private function buildrecordIdWithCheckDigit($recordId){
		$sumOfDigits = 0;
		$baseId = str_replace(['.b', 'b'], '', $recordId);
		if (ctype_digit($baseId)) {
			$strlen = strlen($baseId);
			for ($i = 0;$i < $strlen;$i++) {
				$multiplier = (($strlen +1) - $i);
				$sumOfDigits += $multiplier * substr($baseId, $i, 1);
			}
			$modValue = $sumOfDigits % 11;
			if ($modValue == 10) {
				return $recordId . 'x';
			}
			return $recordId . $modValue;
		}
		return false;
	}

}
