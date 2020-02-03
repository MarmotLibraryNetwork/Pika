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
 * Allows display of a single image from Islandora
 *
 * @category Pika
 * @author Mark Noble <pika@marmot.org>
 * Date: 9/8/2015
 * Time: 8:43 PM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_LargeImage extends Archive_Object{
	function launch() {
		global $interface;
		global $configArray;

		$hasLargeImage = false;
		$this->loadArchiveObjectData();
		//$this->loadExploreMoreContent();

		$hasImage = false;
		if ($this->archiveObject->getDatastream('JP2') != null) {
			$interface->assign('large_image', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/JP2/view");
			$hasImage = true;
		}
		if ($this->archiveObject->getDatastream('JPG') != null){
			if ($hasImage == false) {
				$interface->assign('image', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/JPG/view");
				$hasImage = true;
			}
		}
		if ($this->archiveObject->getDatastream('LC') != null){
			if ($hasImage == false) {
				$interface->assign('image', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/LC/view");
				$hasImage = true;
			}
			$hasLargeImage = true;
		}
		if ($this->archiveObject->getDatastream('MC') != null){
			if ($hasImage == false) {
				$interface->assign('image', $configArray['Islandora']['objectUrl'] . "/{$this->pid}/datastream/MC/view");
				$hasImage = true;
			}
		}
		if (!$hasImage){
			$interface->assign('noImage', true);
		}
		$interface->assign('hasLargeImage', $hasLargeImage);

		$interface->assign('showExploreMore', true);

		// Display Page
		$this->display('largeImage.tpl');
	}
}
