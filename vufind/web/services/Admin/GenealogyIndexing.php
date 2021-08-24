<?php
/*
 * Copyright (C) 2021  Marmot Library Network
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
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 3/8/2021
 *
 */



require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/Genealogy/Person.php';

class Admin_GenealogyIndexing extends Admin_Admin {

	function launch(){
		global $pikaLogger;
		if (!UserAccount::userHasRole('genealogyContributor')){
			// constructor ensures user is opacAdmin; this ensure the user *also* has genealogy role
			$this->display('../Admin/noPermission.tpl', 'Access Error');
			die;
		}

		set_time_limit(6000);

		global $configArray,
		$solrScope;
		/** @var \SearchObject_Genealogy $searchObject */
		$solrScope    = 'genealogy';  // A hack to fetch correct fields with getRecord()
		$searchObject = \SearchObjectFactory::initSearchObject($configArray['Genealogy']['searchObject']);
		$solr         = $searchObject->getIndexEngine();
		$latestDoc    = $solr->search('*:*', null, null, 0, 1, null, null, null, 'shortId desc', 'id');
		$lastPersonId = $latestDoc['response']['docs'][0]['id'] ?? null;

		echo '<pre>';
		$person = new Person();
		$person->orderBy('personId asc');
		if ($lastPersonId){
			$person->whereAdd('personId > '. str_replace('person', '', $lastPersonId ));
		}
		if ($person->find()){
			$objectStructure = $person->getObjectStructure();
			$i = $j = $k = 0;
			$docs = $ids = [];

			while ($person->fetch()){

						$record = $searchObject->getRecord($person->solrId(), 'id');
						if (!empty($record)){
							if (PEAR_Singleton::isError($record)){
								echo "Solr Error\n";
							}
						} else {
							$this->populateBatchAndAddToSolr($objectStructure, $person, $docs, $j, $solr, $k);
						}


				if ($i++ >= 2000){
					ob_flush();
					$i = 0;
				}
			}
			$this->populateBatchAndAddToSolr($objectStructure, $person, $docs, $j, $solr, $k, true);

		}
		echo "Completed.\n";
		echo '<pre>';
		ob_flush();

	}

	function getAllowableRoles(){
			return ['opacAdmin'];
	}

	/**
	 * @param array $objectStructure
	 * @param Person $person
	 * @return array|mixed
	 */
	private function populateSolrDoc(array $objectStructure, Person $person){
		$doc = [];
		foreach ($objectStructure as $property){
			if (!empty($property['storeSolr']) || !empty($property['properties'])){
				$doc = $person->updateSolrDocumentForProperty($doc, $property);
			}
		}
		return $doc;
	}

	/**
	 * @param array $objectStructure
	 * @param Person $person
	 * @param array $docs
	 * @param int $j
	 * @param Solr $solr
	 * @param int $k
	 */
	private function populateBatchAndAddToSolr(array &$objectStructure, Person &$person, array &$docs, int &$j, Solr &$solr, int &$k, $lastRound = false){
		$docs[] = $this->populateSolrDoc($objectStructure, $person);
		if (++$j == 100 || $lastRound){
			$json  = $person->jsonUTF8EncodeResponse($docs);
			$added = $solr->saveRecord($json);
			if (!$added){
				echo "Failed to index batch with person id {$person->solrId()}\n";
			}
			$j    = 0;
			$docs = [];
			if (++$k == 10 || $lastRound){
				$solr->commit();
				$k = 0;
			}
		}
	}
}