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

/**
 * The Loan Rules are for Sierra systems
 */
require_once 'DB/DataObject.php';

class LoanRule extends DB_DataObject {
	public $__table = 'loan_rules';   // table name
	public $id;
	public $loanRuleId;
	public $name;
	public $code;
	public $normalLoanPeriod;
	public $holdable;
	public $bookable;
	public $homePickup;
	public $shippable;

	function keys(){
		return ['id'];
	}

	function getObjectStructure(){
		$structure = [
			'id'               => ['property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the p-type within the database', 'hideInLists' => true],
			'loanRuleId'       => ['property' => 'loanRuleId', 'type' => 'integer', 'label' => 'Loan Rule Id', 'description' => 'The id of the loan rule', 'hideInLists' => false],
			'name'             => ['property' => 'name', 'type' => 'text', 'label' => 'Name', 'description' => 'A name for the loan rule'],
			'code'             => ['property' => 'code', 'type' => 'text', 'label' => 'Code', 'description' => 'The code for the loan rule'],
			'normalLoanPeriod' => ['property' => 'normalLoanPeriod', 'type' => 'integer', 'label' => 'Normal Loan Period', 'description' => 'The normal loan period for the loan rule'],
			'holdable'         => ['property' => 'holdable', 'type' => 'checkbox', 'label' => 'Holdable', 'description' => 'Whether or not items are holdable'],
			'bookable'         => ['property' => 'bookable', 'type' => 'checkbox', 'label' => 'Bookable', 'description' => 'Whether or not items are bookable'],
			'homePickup'       => ['property' => 'homePickup', 'type' => 'checkbox', 'label' => 'Home Pickup', 'description' => 'Whether or not items are available for Home Pickup', 'hideInLists' => true],
			'shippable'        => ['property' => 'shippable', 'type' => 'checkbox', 'label' => 'Shippable', 'description' => 'Whether or not items are shippable', 'hideInLists' => true],
		];
		return $structure;
	}

	function insert(){
		if (parent::insert()){
			$this->setFullReindexMarker();
		}

//		/** @var Memcache $memCache */
//		global $memCache;
//		global $instanceName;
//		$memCache->delete($instanceName . '_loan_rules');
	}

	function update($dataObject = false){
		if (parent::update($dataObject)){
			$this->setFullReindexMarker();
		}

//		/** @var Memcache $memCache */
//		global $memCache;
//		global $instanceName;
//		$memCache->delete($instanceName . '_loan_rules');
	}


	private function setFullReindexMarker(): void{
		/** @var User $user */
		$user = UserAccount::getLoggedInUser();
		$indexingProfile = new IndexingProfile();
		if ($indexingProfile->get('sourceName', $user->source)){
			//For now, using the logged-in user's account source for getting to the indexing profile connected to
			// the Sierra loan rules. This will possibly be a bad assumption when multiple ILSes are connected.
			$indexingProfile->changeRequiresReindexing = time();
			$indexingProfile->update();
		}
	}

	/**
	 * Adds a header for this object in the edit form pages
	 * @return string|null
	 */
	function label(){
		if (!empty($this->name)){
			return $this->name;
		}
	}

}
