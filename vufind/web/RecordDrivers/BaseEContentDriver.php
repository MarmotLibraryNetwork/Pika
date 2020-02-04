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
 * Abstract class for Basic eContent Record Driver.
 * Use for eContent collections that are based on MARC data
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
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
