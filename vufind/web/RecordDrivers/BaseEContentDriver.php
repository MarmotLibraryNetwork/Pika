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

	public function getItemActions($itemInfo){
		return $this->createActionsFromUrls($itemInfo['relatedUrls']);
	}

	public function getRecordActions($isAvailable, $isHoldable, $isBookable, $relatedUrls = null, $volumeData = null){
		return $this->createActionsFromUrls($relatedUrls);
	}

	private function createActionsFromUrls($relatedUrls){
		$actions = array();
		foreach ($relatedUrls as $urlInfo){
			//Revert to access online per Karen at CCU.  If people want to switch it back, we can add a per library switch
			//$title = 'Online ' . $urlInfo['source'];
			$title     = translate('externalEcontent_url_action');
			$alt       = 'Available online from ' . $urlInfo['source'];
			$fileOrUrl = isset($urlInfo['url']) ? $urlInfo['url'] : $urlInfo['file'];
			if (strlen($fileOrUrl) > 0){
				if (strlen($fileOrUrl) >= 3){
					$extension = strtolower(substr($fileOrUrl, strlen($fileOrUrl), 3));
					if ($extension == 'pdf'){
						$title = 'Access PDF';
					}
				}
				$actions[] = array(
					'url'          => $fileOrUrl,
					'title'        => $title,
					'requireLogin' => false,
					'alt'          => $alt,
				);
			}
		}

		return $actions;
	}

	/**
	 * Override the ils record function
	 * @return null
	 */
	function getNumHolds(){
		return null;
	}

	/**
	 * Override the record function
	 * @return null
	 */
	function getVolumeHolds($volumeData){
		return null;
	}

}
