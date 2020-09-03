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
 * Authentication Profile information to configure how users should be authenticated
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 7/20/2015
 * Time: 4:48 PM
 */

class AccountProfile extends DB_DataObject {
	public $__table = 'account_profiles';    // table name

	public $id;
	public $name;
	public $driver;
	public $loginConfiguration;
	public $authenticationMethod;
	public $vendorOpacUrl;
	public $patronApiUrl;
	public $recordSource;
	public $weight;

	function getObjectStructure(){
		$structure = array(
			'id'                   => array('property' => 'id', 'type' => 'label', 'label' => 'Id', 'description' => 'The unique id within the database'),
			'weight'               => array('property' => 'weight', 'type' => 'integer', 'label' => 'Weight', 'description' => 'The sort order of the book store', 'default' => 0),
			'name'                 => array('property' => 'name', 'type' => 'text', 'label' => 'Name', 'maxLength' => 50, 'description' => 'A name for this indexing profile', 'required' => true),
			'driver'               => array('property' => 'driver', 'type' => 'text', 'label' => 'Driver', 'maxLength' => 50, 'description' => 'The name of the driver to use for authentication', 'required' => true),
			'loginConfiguration'   => array('property' => 'loginConfiguration', 'type' => 'enum', 'label' => 'Login Configuration', 'values' => array('barcode_pin' => 'Barcode and Pin', 'name_barcode' => 'Name and Barcode'), 'description' => 'How to configure the prompts for this authentication profile', 'required' => true),
			'authenticationMethod' => array('property' => 'authenticationMethod', 'type' => 'enum', 'label' => 'Authentication Method', 'values' => array('ils' => 'ILS', 'sip2' => 'SIP 2', 'db' => 'Database', 'ldap' => 'LDAP'), 'description' => 'The method of authentication to use', 'required' => true),
			'vendorOpacUrl'        => array('property' => 'vendorOpacUrl', 'type' => 'text', 'label' => 'Vendor OPAC Url', 'maxLength' => 100, 'description' => 'A link to the url for the vendor opac', 'required' => true),
			'patronApiUrl'         => array('property' => 'patronApiUrl', 'type' => 'text', 'label' => 'Patron API Url', 'maxLength' => 100, 'description' => 'A link to the patron api for the vendor opac if any', 'required' => false),
			'recordSource'         => array('property' => 'recordSource', 'type' => 'text', 'label' => 'Record Source', 'maxLength' => 50, 'description' => 'The record source of checkouts holds, etc.  Should match the source name of an Indexing Profile (not the display name).', 'required' => false),
		);
		return $structure;
	}

	function label(){
		if (!empty($this->name)){
			return $this->name;
		}
	}

	function insert(){
		$this->clearAccountProfilesFromMemCache();
		return parent::insert();
	}

	function update($dataObject = false){
		$this->clearAccountProfilesFromMemCache();
		return parent::update($dataObject);
	}

	function delete($useWhere = false){
		$this->clearAccountProfilesFromMemCache();
		return parent::delete($useWhere);
	}

	private function clearAccountProfilesFromMemCache(){
		/** @var Memcache $memCache */
		global $memCache;
		global $instanceName;
		$memCache->delete('account_profiles_' . $instanceName);
	}
}
