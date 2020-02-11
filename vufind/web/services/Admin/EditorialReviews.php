<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/1/2020
 *
 */
require_once ROOT_DIR . '/sys/LocalEnrichment/EditorialReview.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class EditorialReviews extends ObjectEditor {

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'EditorialReview';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'EditorialReviews';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Editorial Reviews';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects(){
		$object = new EditorialReview();
		$object->orderBy('pubDate DESC');
		$object->find();
		$objectList = array();
		while ($object->fetch()){
			$objectList[$object->editorialReviewId] = clone $object;
		}
		return $objectList;
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return EditorialReview::getObjectStructure();
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'editorialReviewId';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'editorialReviewId';
	}

}