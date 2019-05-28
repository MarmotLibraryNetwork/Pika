<?php
/**
 * eContent Record Driver to handle data for Side Loaded collections.
 * Each side loaded collection is specified by an indexing profile stored in the database.
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 12/18/14
 * Time: 10:50 AM
 */

require_once ROOT_DIR . '/RecordDrivers/BaseEContentDriver.php';
class SideLoadedRecord extends BaseEContentDriver {

	public function getMoreDetailsOptions(){
		global $interface;

		$isbn = $this->getCleanISBN();

		//Load table of contents
		$tableOfContents = $this->getTOC();
		$interface->assign('tableOfContents', $tableOfContents);

		//Get Related Records to make sure we initialize items
		$recordInfo = $this->getGroupedWorkDriver()->getRelatedRecord($this->getIdWithSource());

		//Get copies for the record
		$this->assignCopiesInformation();

		$interface->assign('items', $recordInfo['itemSummary']);

		//Load more details options
		$moreDetailsOptions = $this->getBaseMoreDetailsOptions($isbn);

		$moreDetailsOptions['copies'] = array(
				'label' => 'Copies',
				'body' => $interface->fetch('ExternalEContent/view-items.tpl'),
				'openByDefault' => true
		);

		$moreDetailsOptions['moreDetails'] = array(
				'label' => 'More Details',
				'body' => $interface->fetch('ExternalEContent/view-more-details.tpl'),
		);

		$this->loadSubjects();
		$moreDetailsOptions['subjects'] = array(
				'label' => 'Subjects',
				'body' => $interface->fetch('Record/view-subjects.tpl'),
		);
		$moreDetailsOptions['citations'] = array(
				'label' => 'Citations',
				'body' => $interface->fetch('Record/cite.tpl'),
		);
		if ($interface->getVariable('showStaffView')){
			$moreDetailsOptions['staff'] = array(
					'label' => 'Staff View',
					'body' => $interface->fetch($this->getStaffView()),
			);
		}

		return $this->filterAndSortMoreDetailsOptions($moreDetailsOptions);
	}

	/**
	 * Return the unique identifier of this record within the Solr index;
	 * useful for retrieving additional information (like tags and user
	 * comments) from the external MySQL database.
	 *
	 * @access  public
	 * @return  string              Unique identifier.
	 */
	public function getShortId(){
		return $this->id;
	}

	protected function getRecordType(){
		return $this->profileType;
	}

	function getNumHolds(){
		return 0;
	}

	function getFormats(){
		return $this->getFormat();
	}

	/**
	 * This method is strictly for Physical Records.
	 * This overwrites functionality in the Marc Record Driver
	 * @return bool
	 */
	public function hasOpacFieldMessage(){
		return false;
	}

}