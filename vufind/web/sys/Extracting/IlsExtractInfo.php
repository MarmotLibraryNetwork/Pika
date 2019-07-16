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


}