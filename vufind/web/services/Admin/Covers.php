<?php

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class Covers extends ObjectEditor {

	function getAllowableRoles(){
		return ['opacAdmin', 'libraryAdmin', 'cataloging'];
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'Cover';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'Covers';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Custom Covers';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		$user  = UserAccount::getLoggedInUser();
		$cover = new Cover();
		$cover->find();
		$coverList = [];
		while ($cover->fetch()){
			$coverList[$cover->coverId] = clone $cover;
		}
		return $coverList;
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return Cover::getObjectStructure();
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'coverId';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'coverId';
	}

	function getInstructions(){
		return 'For more information about records and custom covers, see the <a href="https://marmot-support.atlassian.net/l/c/t017oAV0">online documentation</a>.';
	}
}