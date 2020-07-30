<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 2/1/2020
 *
 */
require_once ROOT_DIR . '/sys/LocalEnrichment/LibrarianReview.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';

class LibrarianReviews extends ObjectEditor {

	function getAllowableRoles(){
		return array('opacAdmin', 'libraryAdmin', 'contentEditor', 'libraryManager', 'locationManager');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectType(){
		return 'LibrarianReview';
	}

	/**
	 * @inheritDoc
	 */
	function getToolName(){
		return 'LibrarianReviews';
	}

	/**
	 * @inheritDoc
	 */
	function getPageTitle(){
		return 'Librarian Reviews';
	}

	/**
	 * @inheritDoc
	 */
	function getAllObjects($orderBy = null){
		return parent::getAllObjects($orderBy ?? 'pubDate DESC');
	}

	/**
	 * @inheritDoc
	 */
	function getObjectStructure(){
		return LibrarianReview::getObjectStructure();
	}

	/**
	 * @inheritDoc
	 */
	function getPrimaryKeyColumn(){
		return 'id';
	}

	/**
	 * @inheritDoc
	 */
	function getIdKeyColumn(){
		return 'id';
	}

}