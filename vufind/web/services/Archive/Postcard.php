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
 * @author Mark Noble <mark@marmot.org>
 * Date: 9/8/2015
 * Time: 8:43 PM
 */

require_once ROOT_DIR . '/services/Archive/Object.php';
class Archive_Postcard extends Archive_Object{
	function launch() {
		global $interface;
		global $configArray;
		$this->loadArchiveObjectData();
		//$this->loadExploreMoreContent();

		//Get the front of the object
		$fedoraUtils = FedoraUtils::getInstance();
		$postCardSides = $fedoraUtils->getCompoundObjectParts($this->pid);

		$front = $fedoraUtils->getObject($postCardSides[1]['pid']);
		$back = $fedoraUtils->getObject($postCardSides[2]['pid']);
		if ($front->getDatastream('JP2') != null) {
			$interface->assign('front_image', $configArray['Islandora']['objectUrl'] . "/{$front->id}/datastream/JP2/view");
		}
		if ($front->getDatastream('MC') != null){
			$interface->assign('front_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$front->id}/datastream/MC/view");
		}elseif ($front->getDatastream('SC') != null){
			$interface->assign('front_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$front->id}/datastream/SC/view");
		}elseif ($front->getDatastream('TN') != null){
			$interface->assign('front_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$front->id}/datastream/TN/view");
		}

		if ($back->getDatastream('JP2') != null) {
			$interface->assign('back_image', $configArray['Islandora']['objectUrl'] . "/{$back->id}/datastream/JP2/view");
		}
		if ($back->getDatastream('MC') != null){
			$interface->assign('back_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$back->id}/datastream/MC/view");
		}elseif ($back->getDatastream('SC') != null){
			$interface->assign('back_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$back->id}/datastream/SC/view");
		}elseif ($back->getDatastream('TN') != null){
			$interface->assign('back_thumbnail', $configArray['Islandora']['objectUrl'] . "/{$back->id}/datastream/TN/view");
		}

		$interface->assign('showExploreMore', true);

		// Display Page
		$this->display('postcard.tpl');
	}


}
