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
 * Table Definition for Loan Rules
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
		return array('id');
	}

	function getObjectStructure(){
		$structure = array(
			'id'               => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id of the p-type within the database', 'hideInLists' => true),
			'loanRuleId'       => array('property' => 'loanRuleId', 'type' => 'integer', 'label' => 'Loan Rule Id', 'description' => 'The id of the loan rule', 'hideInLists' => false),
			'name'             => array('property' => 'name', 'type' => 'text', 'label' => 'Name', 'description' => 'A name for the loan rule'),
			'code'             => array('property' => 'code', 'type' => 'text', 'label' => 'Code', 'description' => 'The code for the loan rule'),
			'normalLoanPeriod' => array('property' => 'normalLoanPeriod', 'type' => 'integer', 'label' => 'Normal Loan Period', 'description' => 'The normal loan period for the loan rule'),
			'holdable'         => array('property' => 'holdable', 'type' => 'checkbox', 'label' => 'Holdable', 'description' => 'Whether or not items are holdable'),
			'bookable'         => array('property' => 'bookable', 'type' => 'checkbox', 'label' => 'Bookable', 'description' => 'Whether or not items are bookable'),
			'homePickup'       => array('property' => 'homePickup', 'type' => 'checkbox', 'label' => 'Home Pickup', 'description' => 'Whether or not items are available for Home Pickup', 'hideInLists' => true),
			'shippable'        => array('property' => 'shippable', 'type' => 'checkbox', 'label' => 'Shippable', 'description' => 'Whether or not items are shippable', 'hideInLists' => true),
		);
		return $structure;
	}

	function insert(){
		parent::insert();
//		/** @var Memcache $memCache */
//		global $memCache;
//		global $instanceName;
//		$memCache->delete($instanceName . '_loan_rules');
	}

	function update($dataObject = false){
		parent::update($dataObject);
//		/** @var Memcache $memCache */
//		global $memCache;
//		global $instanceName;
//		$memCache->delete($instanceName . '_loan_rules');
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
