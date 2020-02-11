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

require_once ROOT_DIR  . '/Action.php';
require_once ROOT_DIR  . '/RecordDrivers/Factory.php';
require_once ROOT_DIR . '/services/SourceAndId.php';

abstract class Record_Record extends Action {
	/** @var SourceAndId $sourceAndId */
	public $sourceAndId;

	/** @var MarcRecord|HooplaRecordDriver $recordDriver */
	protected $recordDriver;

	function __construct($record_id = null){
		global $interface;
		global $configArray;
		global $timer;

		//Load basic information needed in subclasses
		$this->sourceAndId = new SourceAndId(empty($record_id) ? $_GET['id'] : $record_id);
		$interface->assign('id', $this->sourceAndId->getRecordId());

		//Check to see if the record exists within the resources table
		$this->recordDriver = RecordDriverFactory::initRecordDriverById($this->sourceAndId);
		if (is_null($this->recordDriver) || !$this->recordDriver->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->displayInvalidRecord();
		}
		$interface->assign('recordDriver', $this->recordDriver);

		$groupedWork = $this->recordDriver->getGroupedWorkDriver();
		if (is_null($groupedWork) || !$groupedWork->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->displayInvalidRecord();
		}

		$this->setClassicViewLinks();

		//Do actions needed if this is the main action.

		if (substr($this->sourceAndId->getRecordId(), 0, 1) == '.'){
			$interface->assign('shortId', substr($this->sourceAndId->getRecordId(), 1));
		}else{
			$interface->assign('shortId', $this->sourceAndId->getRecordId());
		}

		//TODO: This RDF link doesn't seem to work
		$interface->assign('addHeader', '<link rel="alternate" type="application/rdf+xml" title="RDF Representation" href="/Record/' . urlencode($this->sourceAndId->getRecordId()) . '/RDF" />');

		// Retrieve User Search History
		$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false);
		//TODO camel case lastsearch

		// Send down text for inclusion in breadcrumbs
		$interface->assign('breadcrumbText', $this->recordDriver->getBreadcrumb());

		// Send down legal export formats (if any):
		$interface->assign('exportFormats', $this->recordDriver->getExportFormats());

		//Get Next/Previous Links
		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
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
					$catalogConnection  = CatalogFactory::getCatalogConnectionInstance(); // This will use the $activeRecordIndexingProfile to get the catalog connector
					$classicOpacBaseURL = $catalogConnection->accountProfile->vendorOpacUrl;
					if (!empty($classicOpacBaseURL)){
						$classicId = substr($recordId, 1, strlen($recordId) - 2);
						$interface->assign('classicId', $classicId);
						global $searchSource;
						$searchLocation = Location::getSearchLocation($searchSource);
						if (!empty($searchLocation->scope)){
							$sierraOpacScope = $searchLocation->scope;
						}else{
							$searchLibrary   = Library::getSearchLibrary($searchSource);
							$sierraOpacScope = $searchLibrary ? $searchLibrary->scope : (empty($configArray['OPAC']['defaultScope']) ? '93' : $configArray['OPAC']['defaultScope']);
						}
						$interface->assign('classicUrl', $classicOpacBaseURL . "/record=$classicId&amp;searchscope={$sierraOpacScope}");
					}

					break;
				case 'Koha':
					$interface->assign('classicId', $recordId);
					$interface->assign('classicUrl', $configArray['Catalog']['url'] . '/cgi-bin/koha/opac-detail.pl?biblionumber=' . $recordId);
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

		$mainTemplate = $module == "Record" ? 'invalidRecord.tpl' :'../Record/invalidRecord.tpl';
		$this->display($mainTemplate, 'Invalid Record');
		die();
	}

}
