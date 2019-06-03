<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

require_once ROOT_DIR  . '/Action.php';

require_once ROOT_DIR  . '/sys/Language.php';

require_once ROOT_DIR  . '/RecordDrivers/Factory.php';

abstract class Record_Record extends Action
{
	public $source;
	public $id;

	/** @var MarcRecord|HooplaRecordDriver $recordDriver */
	protected $recordDriver;

	/*var File_MARC_Record $marcRecord */
	public $marcRecord;

	public $record;
//	public $similarTitles;

	public $isbn;
//	public $issn;
//	public $upc;

	public $cacheId;

	function __construct($record_id = null){
		global $interface;
		global $configArray;
		global $timer;

//		$interface->assign('page_body_style', 'sidebar_left');

		//Load basic information needed in subclasses
		if ($record_id == null || !isset($record_id)){
			$this->id = $_GET['id'];
		}else{
			$this->id = $record_id;
		}
		if (strpos($this->id, ':')){
			list($source, $id) = explode(":", $this->id);
			$this->source = $source;
			$this->id     = $id;
		}else{
			$this->source = 'ils';
		}
		$interface->assign('id', $this->id);

		//Check to see if the record exists within the resources table
		$this->recordDriver = RecordDriverFactory::initRecordDriverById($this->source . ':' . $this->id);
		if (is_null($this->recordDriver) || !$this->recordDriver->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->displayInvalidRecord();
		}
		$interface->assign('recordDriver', $this->recordDriver);

		$groupedWork = $this->recordDriver->getGroupedWorkDriver();
		if (is_null($groupedWork) || !$groupedWork->isValid()){  // initRecordDriverById itself does a validity check and returns null if not.
			$this->displayInvalidRecord();
		}

		$this->setClassicViewLinks();

		// Define External Content Provider
		//TODO: These template switches don't look to be used any more.
		if (!empty($this->recordDriver->hasReviews())){
			if (isset($configArray['Content']['reviews'])){
				$interface->assign('hasReviews', true);
			}
			if (isset($configArray['Content']['excerpts'])){
				$interface->assign('hasExcerpt', true);
			}
		}

		//Do actions needed if this is the main action.

		//$interface->caching = 1;
		$interface->assign('id', $this->id);
		if (substr($this->id, 0, 1) == '.'){
			$interface->assign('shortId', substr($this->id, 1));
		}else{
			$interface->assign('shortId', $this->id);
		}

		//TODO: This RDF link doesn't seem to work
		$interface->assign('addHeader', '<link rel="alternate" type="application/rdf+xml" title="RDF Representation" href="' . $configArray['Site']['path'] . '/Record/' . urlencode($this->id) . '/RDF" />');

		// Retrieve User Search History
		$interface->assign('lastsearch', isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false);
		//TODO camel case lastsearch

		$this->cacheId = 'Record|' . $_GET['id'] . '|' . get_class($this);

		// Send down text for inclusion in breadcrumbs
		$interface->assign('breadcrumbText', $this->recordDriver->getBreadcrumb());

		// Send down legal export formats (if any):
		$interface->assign('exportFormats', $this->recordDriver->getExportFormats());

		// Set AddThis User
		$interface->assign('addThis', isset($configArray['AddThis']['key']) ?
			$configArray['AddThis']['key'] : false);

		//Get Next/Previous Links
		$searchSource = isset($_REQUEST['searchSource']) ? $_REQUEST['searchSource'] : 'local';
		$searchObject = SearchObjectFactory::initSearchObject();
		$searchObject->init($searchSource);
		$searchObject->getNextPrevLinks();

	}

	/**
	 * Record a record hit to the statistics index when stat tracking is enabled;
	 * this is called by the Home action.
	 */
	public function recordHit(){
	}

	/**
	 * Set any needed URL links to "Classic" Record views.
	 * Typically these are pages that are native to the ILS
	 * that Pika is replacing.
	 */
	protected function setClassicViewLinks(){
		if ($this->source == 'ils'){
			global $configArray;
			global $interface;

			if ($configArray['Catalog']['ils'] == 'Millennium' || $configArray['Catalog']['ils'] == 'Sierra'){
				$classicId = substr($this->id, 1, strlen($this->id) - 2);
				$interface->assign('classicId', $classicId);
				$millenniumScope = $interface->getVariable('millenniumScope');
				if (isset($configArray['Catalog']['linking_url'])){
					$interface->assign('classicUrl', $configArray['Catalog']['linking_url'] . "/record=$classicId&amp;searchscope={$millenniumScope}");
				}

			}elseif ($configArray['Catalog']['ils'] == 'Koha'){
				$interface->assign('classicId', $this->id);
				$interface->assign('classicUrl', $configArray['Catalog']['url'] . '/cgi-bin/koha/opac-detail.pl?biblionumber=' . $this->id);
				$interface->assign('staffClientUrl', $configArray['Catalog']['staffClientUrl'] . '/cgi-bin/koha/catalogue/detail.pl?biblionumber=' . $this->id);
			}elseif ($configArray['Catalog']['ils'] == 'CarlX'){
				$shortId = str_replace('CARL', '', $this->id);
				$shortId = ltrim($shortId, '0');
				$interface->assign('staffClientUrl', $configArray['Catalog']['staffClientUrl'] . '/Items/' . $shortId);
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
