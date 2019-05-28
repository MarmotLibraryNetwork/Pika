<?php
/**
 * Abstract class for Basic eContent Record Driver.
 * Use for eContent collections that are based on MARC data
 *
 * @category Pika
 * @author Mark Noble <mark@marmot.org>
 * Date: 2/9/14
 * Time: 9:50 PM
 */

require_once ROOT_DIR . '/RecordDrivers/MarcRecord.php';

abstract class BaseEContentDriver extends MarcRecord {

	function getQRCodeUrl(){
		global $configArray;
		return $configArray['Site']['url'] . '/qrcode.php?type=' . $this->getModule() . '&id=' . $this->getPermanentId();
	}

}
