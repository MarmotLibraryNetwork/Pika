<?php
/**
 *
 *
 * @category Pika
 * @author: Pascal Brammeier
 * Date: 7/11/2019
 *
 */


class IlsExtractInfo extends DB_DataObject {
	public $__table = 'ils_extract_info';
	public $id;
	public $indexingProfileId;
	public $ilsId;
	public $lastExtracted;
	public $deleted;

	/**
	 * Mark an IlsExtractInfo entry for re-extraction by setting the last extracted timestamp to null.
	 * (Have to fetch an entry first.)
	 *
	 * @return int whether or not the data entry was updated
	 */
	function markForReExtraction(){
		$this->lastExtracted = "null"; // DB Object has special processing to set an column value to null (note: the vufind.ini value is important in this)
		return $this->update();
	}
}