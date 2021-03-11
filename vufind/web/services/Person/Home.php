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

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/RecordDrivers/Factory.php';
require_once ROOT_DIR . '/sys/Search/SearchEntry.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

class Person_Home extends Action {

	function launch(){
		global $interface;
		global $configArray;
		global $timer;

		// Get Person Id
		$id = $_GET['id'];
		$interface->assign('id', $id);
		if (empty($id) || !ctype_digit($id)){
			$this->displayInvalidRecord();
		}

		//Check to see if a user is logged in with admin permissions
		$user        = UserAccount::getLoggedInUser();
		$userIsAdmin = $user && UserAccount::userHasRole('genealogyContributor');
		$interface->assign('userIsAdmin', $userIsAdmin);

		// Load person from the database
		$person = new Person();
		$person->get($id);

		// Setup Search Engine Connection
		$searchObject = SearchObjectFactory::initSearchObject($configArray['Genealogy']['searchObject']);
		$searchObject->init();

		// Retrieve Genealogy Solr Document
		$record = $searchObject->getRecord($person->solrId());
		if (!empty($record)){
			global $pikaLogger;
			$pikaLogger->debug("Did not find a record for person id {$id} in solr.");
			$this->displayInvalidRecord();
		}

		$record['picture'] = $person->picture;

		$interface->assign('record', $record);
		$interface->assign('person', $person);
		$recordDriver = RecordDriverFactory::initRecordDriver($record);
		$interface->assign('recordDriver', $recordDriver);
		$timer->logTime('Initialized the Record Driver');

		$marriages       = [];
		$personMarriages = $person->marriages;
		if (isset($personMarriages)){
			foreach ($personMarriages as $marriage){
				$marriageArray                          = (array)$marriage;
				$marriageArray['formattedMarriageDate'] = $person->formatPartialDate($marriage->marriageDateDay, $marriage->marriageDateMonth, $marriage->marriageDateYear);
				$marriages[]                            = $marriageArray;
			}
		}
		$interface->assign('marriages', $marriages);
		$interface->assign('obituaries', $person->obituaries);

		//Do actions needed if this is the main action.
		$interface->assign('id', $id);

		// Retrieve User Search History
		$interface->assign('lastsearch', $_SESSION['lastSearchURL'] ?? false);

		// Send down text for inclusion in breadcrumbs
		$interface->assign('breadcrumbText', $recordDriver->getBreadcrumb());

		$formattedBirthdate = $person->formatPartialDate($person->birthDateDay, $person->birthDateMonth, $person->birthDateYear);
		$interface->assign('birthDate', $formattedBirthdate);

		$formattedDeathdate = $person->formatPartialDate($person->deathDateDay, $person->deathDateMonth, $person->deathDateYear);
		$interface->assign('deathDate', $formattedDeathdate);

		//Setup next and previous links based on the search results.
		$searchObject->getNextPrevLinks();

		// Display Page
		$titleField = $recordDriver->getName();
		$this->display('view.tpl', $titleField);
	}

	private function displayInvalidRecord(): void{
		$this->display('invalidRecord.tpl', 'Invalid Record');
		die();
	}
}
