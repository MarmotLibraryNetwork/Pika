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
 * Record Driver for display of LargeImages from Islandora
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 12/9/2015
 * Time: 1:47 PM
 */
require_once ROOT_DIR . '/RecordDrivers/IslandoraDriver.php';
class PersonDriver extends IslandoraDriver {

	public function getViewAction() {
		return 'Person';
	}

	protected function getPlaceholderImage() {
		return '/interface/themes/responsive/images/people.png';
	}

	public function isEntity(){
		return true;
	}

	public function getFormat(){
		return 'Person';
	}

	public function getMoreDetailsOptions(){
		//Load more details options
		global $interface;
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions();
		unset($moreDetailsOptions['relatedPlaces']);

		$relatedPlaces             = $this->getRelatedPlaces();
		$unlinkedEntities          = $this->unlinkedEntities;
		$linkedAddresses           = [];
		$unlinkedAddresses         = [];
		$linkedMilitaryAddresses   = [];
		$unlinkedMilitaryAddresses = [];
		foreach ($unlinkedEntities as $key => $tmpEntity){
			if ($tmpEntity['type'] == 'place'){
				if (strcasecmp($tmpEntity['role'], 'ServedInMilitary') === 0){
					$unlinkedMilitaryAddresses[] = $tmpEntity;
				}else{
					$unlinkedAddresses[] = $tmpEntity;
				}
				unset($this->unlinkedEntities[$key]);
				$interface->assign('unlinkedEntities', $this->unlinkedEntities);
			}
		}
		foreach ($relatedPlaces as $key => $tmpEntity){
			if (strcasecmp($tmpEntity['role'], 'ServedInMilitary') === 0){
				$linkedMilitaryAddresses[] = $tmpEntity;
			}else{
				$linkedAddresses[] = $tmpEntity;
			}
			unset($this->relatedPlaces[$key]);
			$interface->assign('relatedPlaces', $this->relatedPlaces);
		}
		$interface->assign('unlinkedAddresses', $unlinkedAddresses);
		$interface->assign('linkedAddresses', $linkedAddresses);
		if (count($linkedAddresses) || count($unlinkedAddresses)){
			$moreDetailsOptions['addresses'] = [
				'label'         => 'Addresses',
				'body'          => $interface->fetch('Archive/addressSection.tpl'),
				'hideByDefault' => false,
			];
		}
		if (!empty($interface->getVariable('creators'))
			|| $this->hasDetails
			|| (!empty($interface->getVariable('marriages')))
			|| (!empty($this->unlinkedEntities))){
			$moreDetailsOptions['details'] = [
				'label'         => 'Details',
				'body'          => $interface->fetch('Archive/detailsSection.tpl'),
				'hideByDefault' => false
			];
		}else{
			unset($moreDetailsOptions['details']);
		}

		$relatedPeople = $this->getRelatedPeople();
		if (count($relatedPeople)){
			$moreDetailsOptions['familyDetails'] = array(
				'label'         => 'Family Details',
				'body'          => $interface->fetch('Archive/relatedPeopleSection.tpl'),
				'hideByDefault' => false,
			);
			unset($moreDetailsOptions['relatedPeople']);
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}
}
